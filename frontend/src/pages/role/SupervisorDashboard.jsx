import PsychologyAltOutlinedIcon from '@mui/icons-material/PsychologyAltOutlined';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  CircularProgress,
  Dialog,
  DialogContent,
  DialogTitle,
  Grid2,
  MenuItem,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import dayjs from 'dayjs';
import { useEffect, useState } from 'react';
import { RouteMap } from '../../components/RouteMap';
import { StatCard } from '../../components/StatCard';
import { api } from '../../services/api';

const JORNADA_ESTADOS = ['borrador', 'activa', 'completada', 'cancelada'];

export function SupervisorDashboard({ token }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [consultores, setConsultores] = useState([]);
  const [jornadas, setJornadas] = useState([]);
  const [lotes, setLotes] = useState([]);
  const [selectedLoteId, setSelectedLoteId] = useState('');
  const [assignConsultorId, setAssignConsultorId] = useState('');

  const [newJornada, setNewJornada] = useState({ consultor_id: '', fecha: dayjs().format('YYYY-MM-DD') });
  const [routeFilters, setRouteFilters] = useState({ seccion_id: '', servicio: '' });

  const [selectedJornada, setSelectedJornada] = useState(null);
  const [asignaciones, setAsignaciones] = useState([]);
  const [domicilioDetalle, setDomicilioDetalle] = useState(null);
  const [domicilioOpen, setDomicilioOpen] = useState(false);

  const loadData = async () => {
    setLoading(true);
    setError('');
    try {
      const now = dayjs();
      const [consultoresData, jornadasData, lotesData] = await Promise.all([
        api.getUsuarios(token, 'consultor'),
        api.getJornadas(token),
        api.getLotes(token, `anio=${now.year()}&mes=${now.month() + 1}`),
      ]);
      setConsultores(consultoresData);
      setJornadas(jornadasData);
      setLotes(lotesData);
      if (!newJornada.consultor_id && consultoresData[0]) {
        setNewJornada((prev) => ({ ...prev, consultor_id: String(consultoresData[0].id) }));
      }
      if (!assignConsultorId && consultoresData[0]) {
        setAssignConsultorId(String(consultoresData[0].id));
      }
      if (!selectedLoteId && lotesData[0]) {
        setSelectedLoteId(String(lotesData[0].id));
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  const createJornada = async (event) => {
    event.preventDefault();
    setError('');
    setNotice('');

    try {
      await api.createJornada(token, {
        consultor_id: Number(newJornada.consultor_id),
        fecha: newJornada.fecha,
      });
      setNotice('Jornada creada correctamente.');
      await loadData();
    } catch (err) {
      setError(err.message);
    }
  };

  const generarRuta = async (jornadaId) => {
    setError('');
    setNotice('');

    try {
      const payload = {
        seccion_id: routeFilters.seccion_id ? Number(routeFilters.seccion_id) : undefined,
        servicio: routeFilters.servicio || undefined,
      };
      await api.generarRuta(token, jornadaId, payload);
      setNotice('Ruta generada y asignada a la jornada.');
      await loadData();
    } catch (err) {
      setError(err.message);
    }
  };

  const cambiarEstado = async (jornadaId, estado) => {
    try {
      await api.updateJornadaEstado(token, jornadaId, estado);
      setNotice('Estado actualizado.');
      await loadData();
    } catch (err) {
      setError(err.message);
    }
  };

  const verAsignaciones = async (jornada) => {
    try {
      const [jornadaFresh, items] = await Promise.all([
        api.getJornadaById(token, jornada.id),
        api.getJornadaAsignaciones(token, jornada.id),
      ]);
      setSelectedJornada(jornadaFresh);
      setAsignaciones(items);
    } catch (err) {
      setError(err.message);
    }
  };

  const verDomicilio = async (domicilioId) => {
    try {
      const detalle = await api.getDomicilioById(token, domicilioId);
      setDomicilioDetalle(detalle);
      setDomicilioOpen(true);
    } catch (err) {
      setError(err.message);
    }
  };

  const ejecutarLlm = async (jornadaId) => {
    setError('');
    try {
      await api.priorizarJornada(token, jornadaId);
      setNotice('Sugerencia LLM solicitada.');
    } catch (err) {
      setError(err.message);
    }
  };

  const asignarLoteConsultor = async () => {
    if (!selectedLoteId || !assignConsultorId) {
      setError('Selecciona lote y consultor para asignar.');
      return;
    }
    setError('');
    try {
      await api.assignLoteToConsultor(token, Number(selectedLoteId), { consultor_id: Number(assignConsultorId) });
      setNotice('Lote asignado al consultor.');
    } catch (err) {
      setError(err.message);
    }
  };

  if (loading) {
    return (
      <Stack spacing={1.5} alignItems="center" py={6}>
        <CircularProgress />
        <Typography>Cargando panel de supervision...</Typography>
      </Stack>
    );
  }

  return (
    <Stack spacing={2.5}>
      <Box>
        <Typography variant="h5">Panel Supervisor</Typography>
        <Typography color="text.secondary">Planificacion de jornadas y control operativo de rutas.</Typography>
      </Box>

      {error ? <Alert severity="error" onClose={() => setError('')}>{error}</Alert> : null}
      {notice ? <Alert severity="success" onClose={() => setNotice('')}>{notice}</Alert> : null}

      <Grid2 container spacing={2}>
        <Grid2 size={{ xs: 12, md: 4 }}><StatCard title="Consultores" value={consultores.length} hint="Disponibles para asignar" /></Grid2>
        <Grid2 size={{ xs: 12, md: 4 }}><StatCard title="Jornadas" value={jornadas.length} hint="Historico visible" /></Grid2>
        <Grid2 size={{ xs: 12, md: 4 }}><StatCard title="Activas" value={jornadas.filter((j) => j.estado === 'activa').length} hint="En ejecucion" /></Grid2>
      </Grid2>

      <Card>
        <CardContent>
          <Typography variant="h6" mb={1.2}>Asignacion de lotes mensuales</Typography>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.2}>
            <TextField
              select
              label="Lote del mes"
              value={selectedLoteId}
              onChange={(e) => setSelectedLoteId(e.target.value)}
              sx={{ minWidth: 280 }}
            >
              {lotes.map((l) => (
                <MenuItem key={l.id} value={String(l.id)}>
                  #{l.numero_lote} - {l.titulo} ({l.total_domicilios} domicilios)
                </MenuItem>
              ))}
            </TextField>
            <TextField
              select
              label="Consultor"
              value={assignConsultorId}
              onChange={(e) => setAssignConsultorId(e.target.value)}
              sx={{ minWidth: 240 }}
            >
              {consultores.map((c) => (
                <MenuItem key={c.id} value={String(c.id)}>{c.nombre}</MenuItem>
              ))}
            </TextField>
            <Button variant="contained" onClick={asignarLoteConsultor} disabled={!selectedLoteId || !assignConsultorId}>
              Asignar lote
            </Button>
          </Stack>
        </CardContent>
      </Card>

      <Grid2 container spacing={2}>
        <Grid2 size={{ xs: 12, lg: 5 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" mb={1.5}>Crear jornada</Typography>
              <form onSubmit={createJornada}>
                <Stack spacing={1.5}>
                  <TextField select label="Consultor" value={newJornada.consultor_id} onChange={(e) => setNewJornada((p) => ({ ...p, consultor_id: e.target.value }))} required>
                    {consultores.map((c) => (
                      <MenuItem key={c.id} value={String(c.id)}>{c.nombre}</MenuItem>
                    ))}
                  </TextField>
                  <TextField label="Fecha" type="date" value={newJornada.fecha} onChange={(e) => setNewJornada((p) => ({ ...p, fecha: e.target.value }))} InputLabelProps={{ shrink: true }} required />
                  <Button type="submit" variant="contained">Crear jornada</Button>
                </Stack>
              </form>

              <Typography variant="h6" mt={3} mb={1.5}>Filtro para generar ruta</Typography>
              <Stack spacing={1.5}>
                <TextField label="Seccion ID" value={routeFilters.seccion_id} onChange={(e) => setRouteFilters((p) => ({ ...p, seccion_id: e.target.value }))} />
                <TextField select label="Servicio" value={routeFilters.servicio} onChange={(e) => setRouteFilters((p) => ({ ...p, servicio: e.target.value }))}>
                  <MenuItem value="">Todos</MenuItem>
                  <MenuItem value="Gas Natural">Gas Natural</MenuItem>
                  <MenuItem value="Servicio Social">Servicio Social</MenuItem>
                </TextField>
              </Stack>
            </CardContent>
          </Card>
        </Grid2>

        <Grid2 size={{ xs: 12, lg: 7 }}>
          <Card>
            <CardContent>
              <Typography variant="h6" mb={1.2}>Jornadas</Typography>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>ID</TableCell>
                    <TableCell>Fecha</TableCell>
                    <TableCell>Consultor</TableCell>
                    <TableCell>Estado</TableCell>
                    <TableCell>Asignados</TableCell>
                    <TableCell>Acciones</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {jornadas.map((j) => (
                    <TableRow key={j.id} hover>
                      <TableCell>{j.id}</TableCell>
                      <TableCell>{j.fecha}</TableCell>
                      <TableCell>{j.consultor_nombre}</TableCell>
                      <TableCell>{j.estado}</TableCell>
                      <TableCell>{j.total_asignados}</TableCell>
                      <TableCell>
                        <Stack direction="row" spacing={0.8} flexWrap="wrap">
                          <Button size="small" onClick={() => verAsignaciones(j)}>Ver</Button>
                          <Button size="small" variant="outlined" onClick={() => generarRuta(j.id)} disabled={j.estado !== 'borrador'}>
                            Generar
                          </Button>
                          <Button size="small" color="secondary" variant="outlined" startIcon={<PsychologyAltOutlinedIcon />} onClick={() => ejecutarLlm(j.id)}>
                            LLM
                          </Button>
                          {JORNADA_ESTADOS.map((estado) => (
                            <Button key={estado} size="small" onClick={() => cambiarEstado(j.id, estado)} disabled={estado === j.estado}>
                              {estado}
                            </Button>
                          ))}
                        </Stack>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </Grid2>
      </Grid2>

      <Dialog open={Boolean(selectedJornada)} onClose={() => setSelectedJornada(null)} maxWidth="md" fullWidth>
        <DialogTitle>Asignaciones de jornada #{selectedJornada?.id}</DialogTitle>
        <DialogContent>
          <Stack spacing={2}>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Orden</TableCell>
                  <TableCell>Domicilio</TableCell>
                  <TableCell>Seccion</TableCell>
                  <TableCell>Servicio</TableCell>
                  <TableCell>Estado</TableCell>
                  <TableCell>Detalle</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {asignaciones.map((a) => (
                  <TableRow key={a.id}>
                    <TableCell>{a.orden}</TableCell>
                    <TableCell>{a.calle} {a.altura}</TableCell>
                    <TableCell>{a.seccion_numero}</TableCell>
                    <TableCell>{a.servicio}</TableCell>
                    <TableCell>{a.estado}</TableCell>
                    <TableCell>
                      <Button size="small" onClick={() => verDomicilio(a.domicilio_id)}>Ver domicilio</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            <Box>
              <Typography variant="subtitle1" mb={1}>Mapa de asignaciones</Typography>
              <RouteMap points={asignaciones} height={300} />
            </Box>
          </Stack>
        </DialogContent>
      </Dialog>

      <Dialog open={domicilioOpen} onClose={() => setDomicilioOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Detalle de domicilio #{domicilioDetalle?.id}</DialogTitle>
        <DialogContent>
          {domicilioDetalle ? (
            <Stack spacing={1} mt={0.5}>
              <Typography><strong>Direccion:</strong> {domicilioDetalle.calle} {domicilioDetalle.altura}</Typography>
              <Typography><strong>Seccion:</strong> {domicilioDetalle.seccion_numero}</Typography>
              <Typography><strong>Servicio:</strong> {domicilioDetalle.servicio}</Typography>
              <Typography><strong>Provincia/Pais:</strong> {domicilioDetalle.provincia} / {domicilioDetalle.pais}</Typography>
              <Typography><strong>Geocodificado:</strong> {domicilioDetalle.geocodificado ? 'Si' : 'No'}</Typography>
            </Stack>
          ) : null}
        </DialogContent>
      </Dialog>
    </Stack>
  );
}
