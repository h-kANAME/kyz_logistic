import LoginIcon from '@mui/icons-material/Login';
import RouteOutlinedIcon from '@mui/icons-material/RouteOutlined';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  CircularProgress,
  Container,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ThemeSelector } from '../components/ThemeSelector';
import { useAuth } from '../context/AuthContext';

export function LoginPage() {
  const navigate = useNavigate();
  const { login } = useAuth();

  const [form, setForm] = useState({ email: '', password: '' });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setError('');

    try {
      await login(form.email, form.password);
      navigate('/app', { replace: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Box className="page-enter" sx={{ minHeight: '100vh', display: 'grid', placeItems: 'center', py: 4 }}>
      <Container maxWidth="sm">
        <Card>
          <CardContent sx={{ p: { xs: 3, md: 4 } }}>
            <Stack spacing={2.5}>
              <Stack direction="row" justifyContent="flex-end">
                <ThemeSelector minWidth={150} />
              </Stack>

              <Stack direction="row" spacing={1.5} alignItems="center">
                <RouteOutlinedIcon color="primary" />
                <Box>
                  <Typography variant="h5">KYZ Logistica</Typography>
                  <Typography variant="body2" color="text.secondary">
                    Acceso al sistema de ruteo de cobranzas
                  </Typography>
                </Box>
              </Stack>

              <Typography variant="body2" color="text.secondary">
                Ingresar con un usuario activo para ver las vistas segun el rol (Admin, Supervisor o Consultor).
              </Typography>

              {error ? <Alert severity="error">{error}</Alert> : null}

              <form onSubmit={handleSubmit}>
                <Stack spacing={2}>
                  <TextField
                    label="Email"
                    type="email"
                    value={form.email}
                    onChange={(e) => setForm((prev) => ({ ...prev, email: e.target.value }))}
                    required
                    fullWidth
                  />
                  <TextField
                    label="Contrasena"
                    type="password"
                    value={form.password}
                    onChange={(e) => setForm((prev) => ({ ...prev, password: e.target.value }))}
                    required
                    fullWidth
                  />
                  <Button
                    type="submit"
                    variant="contained"
                    size="large"
                    startIcon={loading ? <CircularProgress size={18} color="inherit" /> : <LoginIcon />}
                    disabled={loading}
                  >
                    {loading ? 'Ingresando...' : 'Iniciar sesion'}
                  </Button>
                </Stack>
              </form>
            </Stack>
          </CardContent>
        </Card>
      </Container>
    </Box>
  );
}
