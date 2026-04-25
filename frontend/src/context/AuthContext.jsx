import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { api } from '../services/api';

const AuthContext = createContext(null);

const STORAGE_KEY = 'kyz_auth';

export function AuthProvider({ children }) {
  const [token, setToken] = useState(null);
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) {
      setLoading(false);
      return () => {
        cancelled = true;
      };
    }

    try {
      const session = JSON.parse(raw);
      if (!session?.token || !session?.user) {
        throw new Error('Sesion invalida');
      }
      (async () => {
        try {
          const current = await api.me(session.token);
          if (cancelled) return;
          setToken(session.token);
          setUser(current);
          localStorage.setItem(STORAGE_KEY, JSON.stringify({ token: session.token, user: current }));
        } catch {
          if (!cancelled) {
            localStorage.removeItem(STORAGE_KEY);
            setToken(null);
            setUser(null);
          }
        } finally {
          if (!cancelled) {
            setLoading(false);
          }
        }
      })();
    } catch {
      localStorage.removeItem(STORAGE_KEY);
      setLoading(false);
    }

    return () => {
      cancelled = true;
    };
  }, []);

  const saveSession = (nextToken, nextUser) => {
    setToken(nextToken);
    setUser(nextUser);
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ token: nextToken, user: nextUser }));
  };

  const login = async (email, password) => {
    const data = await api.login(email, password);
    saveSession(data.token, data.user);
    return data.user;
  };

  const refreshMe = async () => {
    if (!token) {
      return null;
    }
    const current = await api.me(token);
    setUser(current);
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ token, user: current }));
    return current;
  };

  const logout = () => {
    setToken(null);
    setUser(null);
    localStorage.removeItem(STORAGE_KEY);
  };

  const value = useMemo(
    () => ({ token, user, loading, isAuthenticated: Boolean(token && user), login, refreshMe, logout }),
    [token, user, loading]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth debe usarse dentro de AuthProvider');
  }
  return context;
}
