import { CircularProgress, Stack, Typography } from '@mui/material';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export function ProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return (
      <Stack alignItems="center" justifyContent="center" minHeight="100vh" spacing={2}>
        <CircularProgress />
        <Typography>Cargando sesion...</Typography>
      </Stack>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return children;
}
