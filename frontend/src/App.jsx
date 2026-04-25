import { Navigate, Route, Routes, useNavigate } from 'react-router-dom';
import DashboardOutlinedIcon from '@mui/icons-material/DashboardOutlined';
import GroupOutlinedIcon from '@mui/icons-material/GroupOutlined';
import MapOutlinedIcon from '@mui/icons-material/MapOutlined';
import ImportExportOutlinedIcon from '@mui/icons-material/ImportExportOutlined';
import ViewModuleOutlinedIcon from '@mui/icons-material/ViewModuleOutlined';
import DirectionsOutlinedIcon from '@mui/icons-material/DirectionsOutlined';
import RouteOutlinedIcon from '@mui/icons-material/RouteOutlined';
import Inventory2OutlinedIcon from '@mui/icons-material/Inventory2Outlined';
import { AppShell } from './components/AppShell';
import { ProtectedRoute } from './components/ProtectedRoute';
import { useAuth } from './context/AuthContext';
import { LoginPage } from './pages/LoginPage';
import { AdminDashboard } from './pages/role/AdminDashboard';
import { ConsultorDashboardLotes } from './pages/role/ConsultorDashboardLotes';
import { SupervisorDashboard } from './pages/role/SupervisorDashboard';

const ADMIN_MODULES = [
  { label: 'Home', href: '#admin-home', icon: <DashboardOutlinedIcon fontSize="small" /> },
  { label: 'Domicilios', href: '#admin-domicilios', icon: <ImportExportOutlinedIcon fontSize="small" /> },
  { label: 'Usuarios', href: '#admin-usuarios', icon: <GroupOutlinedIcon fontSize="small" /> },
  { label: 'Secciones', href: '#admin-secciones', icon: <ViewModuleOutlinedIcon fontSize="small" /> },
  { label: 'Lotes', href: '#admin-lotes', icon: <Inventory2OutlinedIcon fontSize="small" /> },
  { label: 'Jornadas', href: '#admin-jornadas', icon: <MapOutlinedIcon fontSize="small" /> },
];

const CONSULTOR_MODULES = [
  { label: 'Resumen', href: '#consultor-resumen', icon: <DashboardOutlinedIcon /> },
  { label: 'Visita del dia', href: '#consultor-plan-dia', icon: <DirectionsOutlinedIcon /> },
  { label: 'Hojas', href: '#consultor-hojas-ruta', icon: <RouteOutlinedIcon /> },
];

function RoleHome() {
  const navigate = useNavigate();
  const { token, user, logout } = useAuth();

  const modulesByRole = () => {
    if (user?.rol === 'admin') {
      return ADMIN_MODULES;
    }
    if (user?.rol === 'consultor') {
      return CONSULTOR_MODULES;
    }
    return [];
  };

  const renderByRole = () => {
    if (user?.rol === 'admin') {
      return <AdminDashboard token={token} />;
    }
    if (user?.rol === 'supervisor') {
      return <SupervisorDashboard token={token} />;
    }
    return <ConsultorDashboardLotes token={token} />;
  };

  const handleLogout = () => {
    logout();
    navigate('/login', { replace: true });
  };

  return (
    <AppShell user={user} onLogout={handleLogout} modules={modulesByRole()}>
      {renderByRole()}
    </AppShell>
  );
}

export default function App() {
  const { isAuthenticated } = useAuth();

  return (
    <Routes>
      <Route path="/login" element={isAuthenticated ? <Navigate to="/app" replace /> : <LoginPage />} />
      <Route
        path="/app"
        element={
          <ProtectedRoute>
            <RoleHome />
          </ProtectedRoute>
        }
      />
      <Route path="*" element={<Navigate to={isAuthenticated ? '/app' : '/login'} replace />} />
    </Routes>
  );
}
