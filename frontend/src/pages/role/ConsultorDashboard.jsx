import CheckCircleOutlineIcon from '@mui/icons-material/CheckCircleOutline';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';
import DirectionsOutlinedIcon from '@mui/icons-material/DirectionsOutlined';
import EditOutlinedIcon from '@mui/icons-material/EditOutlined';
import OpenInNewOutlinedIcon from '@mui/icons-material/OpenInNewOutlined';
import PushPinOutlinedIcon from '@mui/icons-material/PushPinOutlined';
import RemoveCircleOutlineIcon from '@mui/icons-material/RemoveCircleOutline';
import RestartAltOutlinedIcon from '@mui/icons-material/RestartAltOutlined';
import SaveOutlinedIcon from '@mui/icons-material/SaveOutlined';
import TaskAltOutlinedIcon from '@mui/icons-material/TaskAltOutlined';
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  FormControlLabel,
  Grid2,
  MenuItem,
  Stack,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import { useEffect, useMemo, useState } from 'react';
import { RouteMap } from '../../components/RouteMap';
import { StatCard } from '../../components/StatCard';
import { api } from '../../services/api';

const RESULTADOS = ['visitado', 'ausente', 'reagendar'];
const MOVILIDADES = [
  { value: 'a_pie', label: 'A pie' },
  { value: 'vehiculo', label: 'Vehiculo' },
  { value: 'autobus', label: 'Autobus' },
  { value: 'bicicleta', label: 'Bicicleta' },
];
const HOJA_ESTADOS = ['validada', 'en_curso', 'completada', 'cancelada'];
const CONSULTOR_VIEWS = ['consultor-resumen', 'consultor-paradas', 'consultor-plan-dia', 'consultor-hojas-ruta'];

export function ConsultorDashboard({ token }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');

  const [currentView, setCurrentView] = useState('consultor-resumen');
  const [jornadas, setJornadas] = useState([]);
  const [jornadaId, setJornadaId] = useState('');

  const [allAsignaciones, setAllAsignaciones] = useState([]);
  const [paradasPage, setParadasPage] = useState({ items: [], total: 0, page: 1, per_page: 10, pages: 1 });
  const [hojasPage, setHojasPage] = useState({ items: [], total: 0, page: 1, per_page: 10, pages: 1 });

  const [perfil, setPerfil] = useState({
    punto_partida_direccion: '',
    punto_partida_latitud: '',
    punto_partida_longitud: '',
    punto_retorno_direccion: '',
    punto_retorno_latitud: '',
    punto_retorno_longitud: '',
    movilidad: 'a_pie',
    disponibilidad_minutos: 240,
  });

  const [planDia, setPlanDia] = useState(null);
  const [excludedIds, setExcludedIds] = useState([]);
  const [forcedFirstId, setForcedFirstId] = useState('');
  const [considerOperationalTime, setConsiderOperationalTime] = useState(false);
  const [operationalMarginMinutes, setOperationalMarginMinutes] = useState(0);
  const [tituloHoja, setTituloHoja] = useState('');

  const [hojaDetalle, setHojaDetalle] = useState(null);
  const [hojaDetalleOpen, setHojaDetalleOpen] = useState(false);
  const [hojaEdit, setHojaEdit] = useState({ id: null, titulo: '', estado: 'validada' });
  const [hojaEditOpen, setHojaEditOpen] = useState(false);

  const [editOpen, setEditOpen] = useState(false);
  const [currentAsignacion, setCurrentAsignacion] = useState(null);
  const [payload, setPayload] = useState({ estado: 'visitado', firmado: 1, observacion: '' });

  useEffect(() => {
    const syncViewFromHash = () => {
      const hash = window.location.hash.replace('#', '');
      if (CONSULTOR_VIEWS.includes(hash)) {
        setCurrentView(hash);
        return;
      }
      window.location.hash = '#consultor-resumen';
      setCurrentView('consultor-resumen');
    };

    syncViewFromHash();
    window.addEventListener('hashchange', syncViewFromHash);
    return () => window.removeEventListener('hashchange', syncViewFromHash);
  }, []);

  const loadJornadas = async () => {
    const data = await api.getJornadas(token);
    setJornadas(data);
    if (!jornadaId && data[0]) {
      setJornadaId(String(data[0].id));
    }
  };

  const loadPerfil = async () => {
    const data = await api.getConsultorPerfil(token);
    setPerfil({
      punto_partida_direccion: data.punto_partida_direccion || '',
      punto_partida_latitud: data.punto_partida_latitud ?? '',
      punto_partida_longitud: data.punto_partida_longitud ?? '',
      punto_retorno_direccion: data.punto_retorno_direccion || data.punto_partida_direccion || '',
      punto_retorno_latitud: data.punto_retorno_latitud ?? data.punto_partida_latitud ?? '',
      punto_retorno_longitud: data.punto_retorno_longitud ?? data.punto_partida_longitud ?? '',
      movilidad: data.movilidad || 'a_pie',
      disponibilidad_minutos: data.disponibilidad_minutos ?? 240,
    });
  };

  const loadAllAsignaciones = async (id) => {
    if (!id) {
      return;
    }
    const items = await api.getJornadaAsignaciones(token, id);
    setAllAsignaciones(items);
  };

  const loadParadasPage = async (id, page = 1, perPage = paradasPage.per_page) => {
    if (!id) {
      return;
    }
    const data = await api.getJornadaAsignacionesPaginadas(token, id, `page=${page}&per_page=${perPage}`);
    setParadasPage(data);
  };

  const loadHojasPage = async (id, page = 1, perPage = hojasPage.per_page) => {
    if (!id) {
      return;
    }
    const data = await api.getHojasRuta(token, `jornada_id=${id}&page=${page}&per_page=${perPage}`);
    setHojasPage(data);
  };

  const loadAll = async () => {
    setLoading(true);
    setError('');
    try {
      await Promise.all([loadJornadas(), loadPerfil()]);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAll();
  }, []);

  useEffect(() => {
    if (!jornadaId) {
      return;
    }

    (async () => {
      try {
        await Promise.all([
          loadAllAsignaciones(jornadaId),
          loadParadasPage(jornadaId, 1, paradasPage.per_page),
          loadHojasPage(jornadaId, 1, hojasPage.per_page),
        ]);
      } catch (err) {
        setError(err.message);
      }
    })();
  }, [jornadaId]);

  const nextPending = useMemo(() => allAsignaciones.find((a) => a.estado === 'pendiente'), [allAsignaciones]);
  const visitadas = useMemo(() => allAsignaciones.filter((a) => a.estado === 'visitado').length, [allAsignaciones]);
  const progreso = allAsignaciones.length ? Math.round((visitadas / allAsignaciones.length) * 100) : 0;

  const openEditor = async (a) => {
    try {
      const fresh = await api.getAsignacionById(token, a.id);
      setCurrentAsignacion(fresh);
      setPayload({
        estado: fresh.estado === 'pendiente' ? 'visitado' : fresh.estado,
        firmado: Number(Boolean(fresh.firmado)),
        observacion: fresh.observacion || '',
      });
      setEditOpen(true);
    } catch (err) {
      setError(err.message);
    }
  };

  const guardarResultado = async () => {
    if (!currentAsignacion) {
      return;
    }

    try {
      await api.updateAsignacion(token, currentAsignacion.id, payload);
      setNotice('Visita actualizada.');
      setEditOpen(false);
      await Promise.all([
        loadAllAsignaciones(jornadaId),
        loadParadasPage(jornadaId, paradasPage.page, paradasPage.per_page),
        loadHojasPage(jornadaId, hojasPage.page, hojasPage.per_page),
      ]);
    } catch (err) {
      setError(err.message);
    }
  };

  const guardarPerfil = async () => {
    setError('');
    setNotice('');
    try {
      const updated = await api.updateConsultorPerfil(token, {
        punto_partida_direccion: perfil.punto_partida_direccion,
        punto_retorno_direccion: perfil.punto_retorno_direccion || perfil.punto_partida_direccion,
        movilidad: perfil.movilidad,
        disponibilidad_minutos: Number(perfil.disponibilidad_minutos),
      });

      setPerfil((prev) => ({
        ...prev,
        punto_partida_latitud: updated.punto_partida_latitud ?? prev.punto_partida_latitud,
        punto_partida_longitud: updated.punto_partida_longitud ?? prev.punto_partida_longitud,
        punto_retorno_latitud: updated.punto_retorno_latitud ?? prev.punto_retorno_latitud,
        punto_retorno_longitud: updated.punto_retorno_longitud ?? prev.punto_retorno_longitud,
        punto_retorno_direccion: updated.punto_retorno_direccion ?? prev.punto_retorno_direccion,
      }));

      setNotice('Perfil operativo actualizado.');
    } catch (err) {
      setError(err.message);
    }
  };

  const limpiarRetruco = () => {
    setExcludedIds([]);
    setForcedFirstId('');
    setNotice('Se limpiaron los retrucos manuales.');
  };

  const toggleExcludeStop = (asignacionId) => {
    setExcludedIds((prev) => (
      prev.includes(asignacionId) ? prev.filter((id) => id !== asignacionId) : [...prev, asignacionId]
    ));
  };

  const pinAsFirst = (asignacionId) => {
    setForcedFirstId((prev) => (String(prev) === String(asignacionId) ? '' : String(asignacionId)));
  };

  const abrirGoogleMaps = (url) => {
    if (!url) {
      return;
    }
    window.open(url, '_blank', 'noopener,noreferrer');
  };

  const generarPlanDia = async () => {
    if (!jornadaId) {
      return;
    }

    setError('');
    setNotice('');
    try {
      const data = await api.planificarJornadaDia(token, jornadaId, {
        punto_partida_direccion: perfil.punto_partida_direccion,
        punto_retorno_direccion: perfil.punto_retorno_direccion || perfil.punto_partida_direccion,
        movilidad: perfil.movilidad,
        disponibilidad_minutos: Number(perfil.disponibilidad_minutos),
        punto_partida_latitud: perfil.punto_partida_latitud || undefined,
        punto_partida_longitud: perfil.punto_partida_longitud || undefined,
        punto_retorno_latitud: perfil.punto_retorno_latitud || undefined,
        punto_retorno_longitud: perfil.punto_retorno_longitud || undefined,
        excluded_asignacion_ids: excludedIds,
        forced_first_asignacion_id: forcedFirstId ? Number(forcedFirstId) : undefined,
        consider_operational_time: considerOperationalTime,
        operational_margin_minutes: Number(operationalMarginMinutes) || 0,
      });
      setPlanDia(data.plan);
      if (!tituloHoja) {
        setTituloHoja(`Hoja ${new Date().toLocaleString('es-AR')}`);
      }
      setNotice('Hoja de ruta propuesta generada. Debes validarla para persistirla.');
    } catch (err) {
      setError(err.message);
    }
  };

  const guardarHojaValidada = async () => {
    if (!jornadaId || !planDia) {
      return;
    }

    try {
      await api.guardarHojaRuta(token, jornadaId, {
        titulo: tituloHoja || undefined,
        estado: 'validada',
        constraints: {
          excluded_asignacion_ids: excludedIds,
          forced_first_asignacion_id: forcedFirstId ? Number(forcedFirstId) : null,
          movilidad: perfil.movilidad,
          disponibilidad_minutos: Number(perfil.disponibilidad_minutos),
          consider_operational_time: considerOperationalTime,
          operational_margin_minutes: Number(operationalMarginMinutes) || 0,
          punto_partida_direccion: perfil.punto_partida_direccion,
          punto_retorno_direccion: perfil.punto_retorno_direccion || perfil.punto_partida_direccion,
        },
        plan: planDia,
      });

      await loadHojasPage(jornadaId, 1, hojasPage.per_page);
      setNotice('Propuesta validada y guardada. Ya puedes seguirla desde Hojas de ruta.');
      window.location.hash = '#consultor-hojas-ruta';
    } catch (err) {
      setError(err.message);
    }
  };

  const abrirDetalleHoja = async (id) => {
    try {
      const data = await api.getHojaRutaById(token, id);
      setHojaDetalle(data);
      setHojaDetalleOpen(true);
    } catch (err) {
      setError(err.message);
    }
  };

  const abrirEdicionHoja = (item) => {
    setHojaEdit({ id: item.id, titulo: item.titulo, estado: item.estado });
    setHojaEditOpen(true);
  };

  const guardarEdicionHoja = async () => {
    try {
      await api.updateHojaRuta(token, hojaEdit.id, {
        titulo: hojaEdit.titulo,
        estado: hojaEdit.estado,
      });
      setHojaEditOpen(false);
      await loadHojasPage(jornadaId, hojasPage.page, hojasPage.per_page);
      setNotice('Hoja de ruta actualizada.');
    } catch (err) {
      setError(err.message);
    }
  };

  const eliminarHoja = async (item) => {
    const ok = window.confirm(`Eliminar la hoja de ruta #${item.id}?`);
    if (!ok) {
      return;
    }

    try {
      await api.deleteHojaRuta(token, item.id);
      await loadHojasPage(jornadaId, 1, hojasPage.per_page);
      setNotice('Hoja de ruta eliminada.');
    } catch (err) {
      setError(err.message);
    }
  };

  const seguirHoja = async (item) => {
    try {
      const full = await api.getHojaRutaById(token, item.id);
      setPlanDia(full.plan);
      window.location.hash = '#consultor-plan-dia';
      setNotice(`Siguiendo hoja #${item.id}: ${item.titulo}`);
    } catch (err) {
      setError(err.message);
    }
  };

  const mapPoints = useMemo(() => {
    if (!planDia) {
      return allAsignaciones;
    }

    const inicio = {
      id: 'start',
      orden: 0,
      calle: planDia.ruta.inicio.direccion || 'Punto de partida',
      altura: '',
      latitud: planDia.ruta.inicio.latitud,
      longitud: planDia.ruta.inicio.longitud,
      servicio: 'Inicio',
      seccion_numero: '-',
    };

    const stops = (planDia.stops || []).map((s, idx) => ({
      id: `p-${s.asignacion_id}`,
      orden: idx + 1,
      calle: s.calle,
      altura: s.altura,
      latitud: s.latitud,
      longitud: s.longitud,
      servicio: s.servicio,
      seccion_numero: s.seccion_numero,
    }));

    const retorno = {
      id: 'end',
      orden: stops.length + 1,
      calle: planDia.ruta?.retorno?.direccion || 'Retorno',
      altura: '',
      latitud: planDia.ruta?.retorno?.latitud,
      longitud: planDia.ruta?.retorno?.longitud,
      servicio: 'Retorno',
      seccion_numero: '-',
    };

    return [inicio, ...stops, retorno];
  }, [planDia, allAsignaciones]);

  if (loading) {
    return (
      <Stack spacing={1.5} alignItems="center" py={6}>
        <CircularProgress />
        <Typography>Cargando jornada del consultor...</Typography>
      </Stack>
    );
  }

  return (
    <Stack spacing={2.5}>
      <Box>
        <Typography variant="h5">Panel Consultor</Typography>
        <Typography color="text.secondary">Constructor de hoja de ruta, persistencia validada y seguimiento de ejecucion.</Typography>
      </Box>

      {error ? <Alert severity="error" onClose={() => setError('')}>{error}</Alert> : null}
      {notice ? <Alert severity="success" onClose={() => setNotice('')}>{notice}</Alert> : null}

      <Card>
        <CardContent>
          <Stack direction={{ xs: 'column', md: 'row' }} spacing={1.5} alignItems={{ md: 'center' }}>
            <TextField
              select
              label="Jornada"
              value={jornadaId}
              onChange={(e) => setJornadaId(e.target.value)}
              sx={{ minWidth: 260 }}
            >
              {jornadas.map((j) => (
                <MenuItem key={j.id} value={String(j.id)}>
                  #{j.id} - {j.fecha} ({j.estado})
                </MenuItem>
              ))}
            </TextField>

            {nextPending ? (
              <Alert icon={<CheckCircleOutlineIcon fontSize="inherit" />} severity="info" sx={{ flexGrow: 1 }}>
                Proxima visita pendiente: #{nextPending.orden} en {nextPending.calle} {nextPending.altura}
              </Alert>
            ) : (
              <Alert severity="success" sx={{ flexGrow: 1 }}>
                No hay visitas pendientes en esta jornada.
              </Alert>
            )}
          </Stack>
        </CardContent>
      </Card>

      {currentView === 'consultor-resumen' ? (
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 12, md: 3 }}><StatCard title="Jornadas" value={jornadas.length} hint="Disponibles" /></Grid2>
          <Grid2 size={{ xs: 12, md: 3 }}><StatCard title="Asignaciones" value={allAsignaciones.length} hint="Paradas del dia" /></Grid2>
          <Grid2 size={{ xs: 12, md: 3 }}><StatCard title="Visitadas" value={visitadas} hint="Resueltas" /></Grid2>
          <Grid2 size={{ xs: 12, md: 3 }}><StatCard title="Progreso" value={`${progreso}%`} hint="Avance de jornada" /></Grid2>
        </Grid2>
      ) : null}

      {currentView === 'consultor-paradas' ? (
        <Card>
          <CardContent>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} justifyContent="space-between" mb={1.5}>
              <Typography variant="h6">Listado de paradas (paginado)</Typography>
              <Stack direction="row" spacing={1}>
                <TextField
                  select
                  size="small"
                  label="Por pagina"
                  value={String(paradasPage.per_page)}
                  onChange={(e) => loadParadasPage(jornadaId, 1, Number(e.target.value))}
                >
                  {[5, 10, 20, 30].map((n) => <MenuItem key={n} value={String(n)}>{n}</MenuItem>)}
                </TextField>
                <Button size="small" disabled={paradasPage.page <= 1} onClick={() => loadParadasPage(jornadaId, paradasPage.page - 1, paradasPage.per_page)}>
                  Anterior
                </Button>
                <Button size="small" disabled={paradasPage.page >= paradasPage.pages} onClick={() => loadParadasPage(jornadaId, paradasPage.page + 1, paradasPage.per_page)}>
                  Siguiente
                </Button>
              </Stack>
            </Stack>

            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>Orden</TableCell>
                  <TableCell>Domicilio</TableCell>
                  <TableCell>Seccion</TableCell>
                  <TableCell>Servicio</TableCell>
                  <TableCell>Estado</TableCell>
                  <TableCell>Accion</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {paradasPage.items.map((a) => (
                  <TableRow key={a.id} hover>
                    <TableCell>{a.orden}</TableCell>
                    <TableCell>{a.calle} {a.altura}</TableCell>
                    <TableCell>{a.seccion_numero}</TableCell>
                    <TableCell>{a.servicio}</TableCell>
                    <TableCell><Chip label={a.estado} size="small" /></TableCell>
                    <TableCell>
                      <Button size="small" variant="contained" onClick={() => openEditor(a)}>Registrar</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            <Typography variant="caption" color="text.secondary" sx={{ mt: 1, display: 'block' }}>
              Pagina {paradasPage.page} de {paradasPage.pages} · Total: {paradasPage.total}
            </Typography>
          </CardContent>
        </Card>
      ) : null}

      {currentView === 'consultor-plan-dia' ? (
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 12, lg: 4 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" mb={1.5}>Visitas del dia (constructor)</Typography>
                <Stack spacing={1.5}>
                  <TextField
                    label="Punto de partida"
                    value={perfil.punto_partida_direccion}
                    onChange={(e) => setPerfil((p) => ({ ...p, punto_partida_direccion: e.target.value }))}
                    helperText="Ej: Guemes 7050, Santa Fe"
                  />
                  <TextField
                    label="Punto de retorno"
                    value={perfil.punto_retorno_direccion}
                    onChange={(e) => setPerfil((p) => ({ ...p, punto_retorno_direccion: e.target.value }))}
                    helperText="Por defecto puede ser igual al punto de partida"
                  />
                  <TextField
                    select
                    label="Movilidad"
                    value={perfil.movilidad}
                    onChange={(e) => setPerfil((p) => ({ ...p, movilidad: e.target.value }))}
                  >
                    {MOVILIDADES.map((m) => <MenuItem key={m.value} value={m.value}>{m.label}</MenuItem>)}
                  </TextField>
                  <TextField
                    label="Disponibilidad (minutos)"
                    type="number"
                    inputProps={{ min: 60, max: 720 }}
                    value={perfil.disponibilidad_minutos}
                    onChange={(e) => setPerfil((p) => ({ ...p, disponibilidad_minutos: Number(e.target.value) }))}
                  />
                  <FormControlLabel
                    control={(
                      <Switch
                        checked={considerOperationalTime}
                        onChange={(e) => setConsiderOperationalTime(e.target.checked)}
                      />
                    )}
                    label="Considerar tiempo operativo"
                  />
                  <TextField
                    label="Margen tolerado (minutos)"
                    type="number"
                    inputProps={{ min: 0, max: 120 }}
                    value={operationalMarginMinutes}
                    onChange={(e) => setOperationalMarginMinutes(Math.max(0, Number(e.target.value) || 0))}
                    helperText="Si se considera operativa, permite aceptar una parada cerca del limite."
                    disabled={!considerOperationalTime}
                  />
                  <TextField
                    label="Titulo hoja"
                    value={tituloHoja}
                    onChange={(e) => setTituloHoja(e.target.value)}
                    helperText="Se usa al validar y guardar la propuesta"
                  />

                  <Divider />
                  <Typography variant="subtitle2">Retruco manual</Typography>
                  <TextField
                    select
                    size="small"
                    label="Forzar primera parada"
                    value={forcedFirstId}
                    onChange={(e) => setForcedFirstId(e.target.value)}
                  >
                    <MenuItem value="">Sin forzar</MenuItem>
                    {allAsignaciones.filter((a) => a.estado === 'pendiente').map((a) => (
                      <MenuItem key={a.id} value={String(a.id)}>
                        #{a.orden} - {a.calle} {a.altura}
                      </MenuItem>
                    ))}
                  </TextField>
                  <Button variant="outlined" startIcon={<RestartAltOutlinedIcon />} onClick={limpiarRetruco}>
                    Limpiar retruco
                  </Button>

                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                    <Button variant="outlined" onClick={guardarPerfil}>Guardar condiciones</Button>
                    <Button variant="contained" startIcon={<DirectionsOutlinedIcon />} onClick={generarPlanDia}>
                      Generar propuesta
                    </Button>
                  </Stack>

                  <Button
                    disabled={!planDia}
                    variant="contained"
                    color="secondary"
                    startIcon={<SaveOutlinedIcon />}
                    onClick={guardarHojaValidada}
                  >
                    Validar y guardar hoja
                  </Button>
                </Stack>
              </CardContent>
            </Card>
          </Grid2>

          <Grid2 size={{ xs: 12, lg: 8 }}>
            <Card>
              <CardContent>
                <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={1} mb={1}>
                  <Typography variant="h6">Propuesta de recorrido</Typography>
                  {planDia?.ruta?.google_maps_url ? (
                    <Button
                      size="small"
                      variant="outlined"
                      startIcon={<OpenInNewOutlinedIcon />}
                      onClick={() => abrirGoogleMaps(planDia.ruta.google_maps_url)}
                    >
                      Abrir ruta en Google Maps
                    </Button>
                  ) : null}
                </Stack>

                {planDia ? (
                  <Stack spacing={1.2}>
                    {(() => {
                      const distanciaParadas = (planDia.stops || []).reduce(
                        (acc, stop) => acc + Number(stop.explanation?.distance_km || 0),
                        0,
                      );
                      const distanciaRetorno = Number(planDia.resumen?.retorno_distancia_km || 0);
                      const distanciaTabla = distanciaParadas + distanciaRetorno;
                      return (
                        <Typography variant="caption" color="text.secondary">
                          Distancia por tramos (tabla): {distanciaTabla.toFixed(2)} km
                        </Typography>
                      );
                    })()}
                    <Typography variant="body2" color="text.secondary">
                      Inicio: {planDia.ruta.inicio.direccion || 'Punto de partida configurado'} · Retorno: {planDia.ruta.retorno.direccion || 'Mismo punto'}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      Planificadas: {planDia.resumen.total_planificadas}/{planDia.resumen.total_pendientes} · Distancia total (incluye retorno): {planDia.resumen.distancia_km} km · Logistica: {planDia.resumen.minutos_logistica ?? planDia.resumen.minutos_estimados} min
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      Operativo: {planDia.resumen.minutos_operativos ?? 0} min · Total c/operativa: {planDia.resumen.minutos_totales_con_operativa ?? planDia.resumen.minutos_estimados} min
                    </Typography>
                    <Typography variant="caption" color="text.secondary">
                      Modo de corte: {considerOperationalTime ? 'Logistica + operativa' : 'Solo logistica'}
                      {considerOperationalTime ? ` · Margen: ${operationalMarginMinutes} min` : ''}
                    </Typography>

                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>#</TableCell>
                          <TableCell>Domicilio</TableCell>
                          <TableCell>Servicio</TableCell>
                          <TableCell>Distancia (km)</TableCell>
                          <TableCell>Acciones</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {planDia.stops.map((s, idx) => {
                          const isExcluded = excludedIds.includes(s.asignacion_id);
                          const isForced = String(forcedFirstId) === String(s.asignacion_id);
                          return (
                            <TableRow
                              key={s.asignacion_id}
                              hover
                            >
                              <TableCell>{idx + 1}</TableCell>
                              <TableCell>{s.calle} {s.altura}</TableCell>
                              <TableCell>{s.servicio}</TableCell>
                              <TableCell>{Number(s.explanation?.distance_km || 0).toFixed(2)}</TableCell>
                              <TableCell>
                                <Stack direction="row" spacing={0.5}>
                                  <Button
                                    size="small"
                                    variant={isExcluded ? 'contained' : 'outlined'}
                                    color={isExcluded ? 'warning' : 'inherit'}
                                    startIcon={<RemoveCircleOutlineIcon />}
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      toggleExcludeStop(s.asignacion_id);
                                    }}
                                  >
                                    Excluir
                                  </Button>
                                  <Button
                                    size="small"
                                    variant={isForced ? 'contained' : 'outlined'}
                                    color={isForced ? 'secondary' : 'inherit'}
                                    startIcon={<PushPinOutlinedIcon />}
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      pinAsFirst(s.asignacion_id);
                                    }}
                                  >
                                    Primera
                                  </Button>
                                  <Button
                                    size="small"
                                    variant="outlined"
                                    startIcon={<OpenInNewOutlinedIcon />}
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      abrirGoogleMaps(s.google_maps_step_url);
                                    }}
                                  >
                                    Maps
                                  </Button>
                                </Stack>
                              </TableCell>
                            </TableRow>
                          );
                        })}
                        {planDia.stops.length ? (
                          <TableRow>
                            <TableCell>{planDia.stops.length + 1}</TableCell>
                            <TableCell>{planDia.ruta?.retorno?.direccion || 'Retorno'}</TableCell>
                            <TableCell>Retorno</TableCell>
                            <TableCell>{Number(planDia.resumen?.retorno_distancia_km || 0).toFixed(2)}</TableCell>
                            <TableCell>-</TableCell>
                          </TableRow>
                        ) : null}
                      </TableBody>
                    </Table>
                  </Stack>
                ) : (
                  <Alert severity="info">Configura condiciones y presiona "Generar propuesta".</Alert>
                )}
              </CardContent>
            </Card>

          </Grid2>
        </Grid2>
      ) : null}

      {currentView === 'consultor-hojas-ruta' ? (
        <Card>
          <CardContent>
            <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} justifyContent="space-between" mb={1.5}>
              <Typography variant="h6">Hojas de ruta guardadas</Typography>
              <Stack direction="row" spacing={1}>
                <Button size="small" disabled={hojasPage.page <= 1} onClick={() => loadHojasPage(jornadaId, hojasPage.page - 1, hojasPage.per_page)}>
                  Anterior
                </Button>
                <Button size="small" disabled={hojasPage.page >= hojasPage.pages} onClick={() => loadHojasPage(jornadaId, hojasPage.page + 1, hojasPage.per_page)}>
                  Siguiente
                </Button>
              </Stack>
            </Stack>

            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>ID</TableCell>
                  <TableCell>Titulo</TableCell>
                  <TableCell>Estado</TableCell>
                  <TableCell>Resumen</TableCell>
                  <TableCell>Creada</TableCell>
                  <TableCell>Acciones</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {hojasPage.items.map((h) => (
                  <TableRow key={h.id} hover>
                    <TableCell>{h.id}</TableCell>
                    <TableCell>{h.titulo}</TableCell>
                    <TableCell><Chip size="small" label={h.estado} /></TableCell>
                    <TableCell>{h.status_summary?.texto || '-'}</TableCell>
                    <TableCell>{h.created_at?.slice(0, 19).replace('T', ' ')}</TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={0.5}>
                        <Button size="small" variant="outlined" startIcon={<VisibilityOutlinedIcon />} onClick={() => abrirDetalleHoja(h.id)}>
                          Ver detalle
                        </Button>
                        <Button size="small" variant="outlined" startIcon={<TaskAltOutlinedIcon />} onClick={() => seguirHoja(h)}>
                          Seguir
                        </Button>
                        <Button size="small" variant="outlined" startIcon={<EditOutlinedIcon />} onClick={() => abrirEdicionHoja(h)}>
                          Editar
                        </Button>
                        <Button size="small" variant="outlined" color="error" startIcon={<DeleteOutlineIcon />} onClick={() => eliminarHoja(h)}>
                          Eliminar
                        </Button>
                      </Stack>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            <Typography variant="caption" color="text.secondary" sx={{ mt: 1, display: 'block' }}>
              Pagina {hojasPage.page} de {hojasPage.pages} · Total: {hojasPage.total}
            </Typography>
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardContent>
          <Typography variant="h6" mb={1.2}>Mapa</Typography>
          <RouteMap points={mapPoints} height={360} />
        </CardContent>
      </Card>

      <Dialog open={hojaDetalleOpen} onClose={() => setHojaDetalleOpen(false)} maxWidth="md" fullWidth>
        <DialogTitle>Detalle de hoja de ruta</DialogTitle>
        <DialogContent>
          {hojaDetalle ? (
            <Stack spacing={1.2} mt={0.5}>
              <Typography variant="body2"><strong>Titulo:</strong> {hojaDetalle.titulo}</Typography>
              <Typography variant="body2"><strong>Estado:</strong> {hojaDetalle.estado}</Typography>
              <Typography variant="body2"><strong>Resumen:</strong> {hojaDetalle.status_summary?.texto || '-'}</Typography>
              {hojaDetalle.plan?.ruta?.google_maps_url ? (
                <Button size="small" variant="outlined" startIcon={<OpenInNewOutlinedIcon />} onClick={() => abrirGoogleMaps(hojaDetalle.plan.ruta.google_maps_url)}>
                  Abrir ruta en Google Maps
                </Button>
              ) : null}
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>#</TableCell>
                    <TableCell>Domicilio</TableCell>
                    <TableCell>Servicio</TableCell>
                    <TableCell>Distancia (km)</TableCell>
                    <TableCell>Maps</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {(hojaDetalle.plan?.stops || []).map((s, idx) => (
                    <TableRow key={s.asignacion_id}>
                      <TableCell>{idx + 1}</TableCell>
                      <TableCell>{s.calle} {s.altura}</TableCell>
                      <TableCell>{s.servicio}</TableCell>
                      <TableCell>{Number(s.explanation?.distance_km || 0).toFixed(2)}</TableCell>
                      <TableCell>
                        <Button size="small" variant="text" onClick={() => abrirGoogleMaps(s.google_maps_step_url)}>Abrir</Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {(hojaDetalle.plan?.stops || []).length ? (
                    <TableRow>
                      <TableCell>{(hojaDetalle.plan?.stops || []).length + 1}</TableCell>
                      <TableCell>{hojaDetalle.plan?.ruta?.retorno?.direccion || 'Retorno'}</TableCell>
                      <TableCell>Retorno</TableCell>
                      <TableCell>{Number(hojaDetalle.plan?.resumen?.retorno_distancia_km || 0).toFixed(2)}</TableCell>
                      <TableCell>-</TableCell>
                    </TableRow>
                  ) : null}
                </TableBody>
              </Table>
            </Stack>
          ) : null}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setHojaDetalleOpen(false)}>Cerrar</Button>
        </DialogActions>
      </Dialog>

      <Dialog open={hojaEditOpen} onClose={() => setHojaEditOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Editar hoja de ruta</DialogTitle>
        <DialogContent>
          <Stack spacing={1.2} mt={0.5}>
            <TextField label="Titulo" value={hojaEdit.titulo} onChange={(e) => setHojaEdit((p) => ({ ...p, titulo: e.target.value }))} />
            <TextField select label="Estado" value={hojaEdit.estado} onChange={(e) => setHojaEdit((p) => ({ ...p, estado: e.target.value }))}>
              {HOJA_ESTADOS.map((estado) => <MenuItem key={estado} value={estado}>{estado}</MenuItem>)}
            </TextField>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setHojaEditOpen(false)}>Cancelar</Button>
          <Button variant="contained" onClick={guardarEdicionHoja}>Guardar</Button>
        </DialogActions>
      </Dialog>

      <Dialog open={editOpen} onClose={() => setEditOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Registrar visita</DialogTitle>
        <DialogContent>
          <Stack spacing={1.5} mt={0.5}>
            <TextField select label="Estado" value={payload.estado} onChange={(e) => setPayload((p) => ({ ...p, estado: e.target.value }))}>
              {RESULTADOS.map((estado) => <MenuItem key={estado} value={estado}>{estado}</MenuItem>)}
            </TextField>
            <TextField select label="Documento firmado" value={String(payload.firmado)} onChange={(e) => setPayload((p) => ({ ...p, firmado: Number(e.target.value) }))}>
              <MenuItem value="1">Si</MenuItem>
              <MenuItem value="0">No</MenuItem>
            </TextField>
            <TextField label="Observacion" minRows={3} multiline value={payload.observacion} onChange={(e) => setPayload((p) => ({ ...p, observacion: e.target.value }))} />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEditOpen(false)}>Cancelar</Button>
          <Button variant="contained" onClick={guardarResultado}>Guardar</Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
