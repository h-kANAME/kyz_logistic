import LogoutOutlinedIcon from '@mui/icons-material/LogoutOutlined';
import MenuOpenOutlinedIcon from '@mui/icons-material/MenuOpenOutlined';
import MenuOutlinedIcon from '@mui/icons-material/MenuOutlined';
import RouteOutlinedIcon from '@mui/icons-material/RouteOutlined';
import {
  AppBar,
  Avatar,
  BottomNavigation,
  BottomNavigationAction,
  Box,
  Button,
  Chip,
  Container,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Paper,
  Stack,
  Toolbar,
  Tooltip,
  Typography,
  useMediaQuery,
  useTheme,
} from '@mui/material';
import { cloneElement, isValidElement, useEffect, useMemo, useState } from 'react';
import { ThemeSelector } from './ThemeSelector';

const SIDEBAR_WIDTH_EXPANDED = 280;
const SIDEBAR_WIDTH_COLLAPSED = 86;

const BOTTOM_NAV_SLOT_PX = 72;

function moduleIcon(mod, size) {
  if (!isValidElement(mod.icon)) return mod.icon;
  return cloneElement(mod.icon, { fontSize: size });
}

export function AppShell({ user, onLogout, children, modules = [] }) {
  const theme = useTheme();
  const isMdUp = useMediaQuery(theme.breakpoints.up('md'));
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [activeModule, setActiveModule] = useState('');

  const sidebarWidth = collapsed ? SIDEBAR_WIDTH_COLLAPSED : SIDEBAR_WIDTH_EXPANDED;
  const hasSidebar = modules.length > 0;
  const consultorBottomNav = hasSidebar && user?.rol === 'consultor' && !isMdUp;
  const showSidebarDrawers = hasSidebar && !consultorBottomNav;

  useEffect(() => {
    const updateFromHash = () => {
      const hash = window.location.hash.replace('#', '');
      setActiveModule(hash);
    };

    updateFromHash();
    window.addEventListener('hashchange', updateFromHash);
    return () => window.removeEventListener('hashchange', updateFromHash);
  }, []);

  const drawerContent = useMemo(
    () => (
      <Box sx={{ height: '100%', display: 'flex', flexDirection: 'column' }}>
        <Stack direction="row" alignItems="center" justifyContent={collapsed ? 'center' : 'space-between'} sx={{ px: 1.5, py: 1.25 }}>
          {!collapsed ? (
            <Typography variant="subtitle2" color="text.secondary" sx={{ letterSpacing: 0.2 }}>
              Modulos
            </Typography>
          ) : null}
          <Tooltip title={collapsed ? 'Desplegar menu' : 'Contraer menu'}>
            <IconButton onClick={() => setCollapsed((prev) => !prev)} size="small">
              {collapsed ? <MenuOutlinedIcon fontSize="small" /> : <MenuOpenOutlinedIcon fontSize="small" />}
            </IconButton>
          </Tooltip>
        </Stack>

        <List disablePadding sx={{ px: 1 }}>
          {modules.map((mod) => {
            const selected = activeModule === mod.href.replace('#', '');
            return (
              <Tooltip key={mod.href} title={collapsed ? mod.label : ''} placement="right">
                <ListItemButton
                  selected={selected}
                  onClick={() => {
                    window.location.hash = mod.href;
                    setActiveModule(mod.href.replace('#', ''));
                    setMobileOpen(false);
                  }}
                  sx={{
                    mb: 0.5,
                    minHeight: 42,
                    borderRadius: 1.5,
                    justifyContent: collapsed ? 'center' : 'flex-start',
                    px: collapsed ? 1 : 1.5,
                  }}
                >
                  <ListItemIcon sx={{ minWidth: collapsed ? 0 : 34, color: 'inherit', justifyContent: 'center' }}>
                    {moduleIcon(mod, 'small')}
                  </ListItemIcon>
                  {!collapsed ? <ListItemText primary={mod.label} primaryTypographyProps={{ variant: 'body2' }} /> : null}
                </ListItemButton>
              </Tooltip>
            );
          })}
        </List>
      </Box>
    ),
    [activeModule, collapsed, modules],
  );

  return (
    <Box
      className="page-enter"
      sx={{
        minHeight: '100vh',
        pb: consultorBottomNav ? 0 : 5,
      }}
    >
      <AppBar elevation={0} position="sticky" sx={{ zIndex: (t) => t.zIndex.drawer + 1 }}>
        <Toolbar
          sx={{
            minHeight: consultorBottomNav ? 52 : undefined,
            px: consultorBottomNav ? 1 : undefined,
            gap: consultorBottomNav ? 0.5 : undefined,
          }}
        >
          {showSidebarDrawers ? (
            <Tooltip title="Abrir navegacion">
              <IconButton color="inherit" sx={{ mr: 1, display: { md: 'none' } }} onClick={() => setMobileOpen(true)}>
                <MenuOutlinedIcon />
              </IconButton>
            </Tooltip>
          ) : null}

          <Stack direction="row" spacing={consultorBottomNav ? 1 : 1.5} alignItems="center" sx={{ flexGrow: 1, minWidth: 0 }}>
            <Avatar sx={{ bgcolor: theme.custom.surfaces.overlaySoft, width: consultorBottomNav ? 34 : 40, height: consultorBottomNav ? 34 : 40 }}>
              <RouteOutlinedIcon sx={{ fontSize: consultorBottomNav ? 18 : 22 }} />
            </Avatar>
            <Box sx={{ minWidth: 0 }}>
              <Typography variant={consultorBottomNav ? 'subtitle1' : 'h6'} noWrap>
                KYZ Logistica
              </Typography>
              <Typography variant="caption" sx={{ opacity: 0.9, display: consultorBottomNav ? 'none' : 'block' }}>
                Ruteo de cobranzas domiciliarias
              </Typography>
            </Box>
          </Stack>

          <Stack direction="row" spacing={consultorBottomNav ? 0.5 : 1} alignItems="center" sx={{ flexShrink: 0 }}>
            <Box sx={{ display: consultorBottomNav ? 'none' : 'block' }}>
              <ThemeSelector darkSurface />
            </Box>

            <Chip
              size="small"
              label={user?.rol || 'sin-rol'}
              sx={{
                bgcolor: theme.custom.surfaces.overlayStrong,
                color: 'white',
                textTransform: 'capitalize',
                maxWidth: consultorBottomNav ? 88 : undefined,
                '& .MuiChip-label': consultorBottomNav ? { px: 0.75, fontSize: '0.7rem' } : undefined,
              }}
            />
            <Typography variant="body2" sx={{ display: { xs: 'none', lg: 'block' }, maxWidth: 160 }} noWrap>
              {user?.nombre}
            </Typography>
            {consultorBottomNav ? (
              <Tooltip title="Cerrar sesion">
                <IconButton color="inherit" onClick={onLogout} aria-label="Cerrar sesion">
                  <LogoutOutlinedIcon />
                </IconButton>
              </Tooltip>
            ) : (
              <Button color="inherit" startIcon={<LogoutOutlinedIcon />} onClick={onLogout}>
                Salir
              </Button>
            )}
          </Stack>
        </Toolbar>
      </AppBar>

      <Box sx={{ display: 'flex', mt: consultorBottomNav ? 1 : 2 }}>
        {showSidebarDrawers ? (
          <>
            <Drawer
              variant="temporary"
              open={mobileOpen}
              onClose={() => setMobileOpen(false)}
              ModalProps={{ keepMounted: true }}
              sx={{
                display: { xs: 'block', md: 'none' },
                '& .MuiDrawer-paper': {
                  width: SIDEBAR_WIDTH_EXPANDED,
                  boxSizing: 'border-box',
                },
              }}
            >
              {drawerContent}
            </Drawer>

            <Drawer
              variant="permanent"
              sx={{
                display: { xs: 'none', md: 'block' },
                width: sidebarWidth,
                flexShrink: 0,
                '& .MuiDrawer-paper': {
                  width: sidebarWidth,
                  transition: 'width 180ms ease',
                  overflowX: 'hidden',
                  boxSizing: 'border-box',
                  borderRight: `1px solid ${theme.palette.divider}`,
                  bgcolor: theme.palette.background.paper,
                },
              }}
            >
              <Toolbar />
              {drawerContent}
            </Drawer>
          </>
        ) : null}

        <Container
          maxWidth="xl"
          disableGutters={consultorBottomNav}
          sx={{
            mt: 0,
            ml: { xs: 0, md: hasSidebar && !consultorBottomNav ? 1 : 0 },
            width: '100%',
            px: consultorBottomNav ? 2 : undefined,
            pb:
              consultorBottomNav
                ? `calc(${BOTTOM_NAV_SLOT_PX}px + env(safe-area-inset-bottom, 0px) + 12px)`
                : undefined,
          }}
        >
          {children}
        </Container>
      </Box>

      {consultorBottomNav ? (
        <Paper
          component="nav"
          square
          elevation={8}
          aria-label="Accesos rapidos del consultor"
          sx={{
            position: 'fixed',
            left: 0,
            right: 0,
            bottom: 0,
            zIndex: theme.zIndex.appBar,
            borderTop: 1,
            borderColor: 'divider',
            bgcolor: theme.palette.background.paper,
            pb: 'env(safe-area-inset-bottom, 0px)',
          }}
        >
          <BottomNavigation
            showLabels
            value={activeModule}
            onChange={(_, newValue) => {
              window.location.hash = `#${newValue}`;
              setActiveModule(newValue);
            }}
            sx={{
              height: BOTTOM_NAV_SLOT_PX,
              '& .MuiBottomNavigationAction-root': {
                minWidth: 0,
                maxWidth: 'none',
                px: 0.5,
              },
              '& .MuiBottomNavigationAction-label': {
                fontSize: '0.65rem',
                whiteSpace: 'normal',
                lineHeight: 1.1,
                '&.Mui-selected': { fontSize: '0.68rem' },
              },
            }}
          >
            {modules.map((mod) => {
              const value = mod.href.replace('#', '');
              return (
                <BottomNavigationAction
                  key={mod.href}
                  label={mod.label}
                  value={value}
                  icon={moduleIcon(mod, 'medium')}
                />
              );
            })}
          </BottomNavigation>
        </Paper>
      ) : null}
    </Box>
  );
}
