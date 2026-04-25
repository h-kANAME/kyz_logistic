import { CssBaseline, ThemeProvider } from '@mui/material';
import { createContext, useContext, useMemo, useState } from 'react';
import { buildAppTheme, DEFAULT_THEME_NAME, THEME_OPTIONS } from '../theme';

const STORAGE_KEY = 'kyz_ui_theme';

const AppThemeContext = createContext(null);

function loadSavedTheme() {
  const saved = localStorage.getItem(STORAGE_KEY);
  if (!saved) {
    return DEFAULT_THEME_NAME;
  }

  return THEME_OPTIONS.some((option) => option.value === saved)
    ? saved
    : DEFAULT_THEME_NAME;
}

export function AppThemeProvider({ children }) {
  const [themeName, setThemeNameState] = useState(loadSavedTheme);

  const theme = useMemo(() => buildAppTheme(themeName), [themeName]);

  const setThemeName = (nextThemeName) => {
    if (!THEME_OPTIONS.some((option) => option.value === nextThemeName)) {
      return;
    }

    setThemeNameState(nextThemeName);
    localStorage.setItem(STORAGE_KEY, nextThemeName);
  };

  const value = useMemo(
    () => ({
      themeName,
      setThemeName,
      themeOptions: THEME_OPTIONS,
    }),
    [themeName]
  );

  return (
    <AppThemeContext.Provider value={value}>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        {children}
      </ThemeProvider>
    </AppThemeContext.Provider>
  );
}

export function useAppTheme() {
  const context = useContext(AppThemeContext);

  if (!context) {
    throw new Error('useAppTheme debe usarse dentro de AppThemeProvider');
  }

  return context;
}
