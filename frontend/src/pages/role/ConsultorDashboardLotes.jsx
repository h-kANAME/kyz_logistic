import OpenInNewOutlinedIcon from '@mui/icons-material/OpenInNewOutlined';
import SaveOutlinedIcon from '@mui/icons-material/SaveOutlined';
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined';
import HistoryOutlinedIcon from '@mui/icons-material/HistoryOutlined';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid2,
  MenuItem,
  Stack,
  Switch,
  FormControlLabel,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
  Chip,
  useMediaQuery,
  useTheme,
} from '@mui/material';
import { useEffect, useMemo, useState } from 'react';
import { RouteMap } from '../../components/RouteMap';
import { api } from '../../services/api';

const CONSULTOR_VIEWS = ['consultor-resumen', 'consultor-plan-dia', 'consultor-hojas-ruta'];

export function ConsultorDashboardLotes({ token }) {
  const theme = useTheme();
  const isCompact = useMediaQuery(theme.breakpoints.down('md'));
  const dialogFullScreen = useMediaQuery(theme.breakpoints.down('sm'));

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('');
  const [currentView, setCurrentView] = useState('consultor-resumen');

  const [lotes, setLotes] = useState([]);
  const [consultorLoteId, setConsultorLoteId] = useState('');
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
  const [considerOperationalTime, setConsiderOperationalTime] = useState(false);
  const [operationalMarginMinutes, setOperationalMarginMinutes] = useState(0);
  const [planDia, setPlanDia] = useState(null);
  const [tituloHoja, setTituloHoja] = useState('');

  const [hojasPage, setHojasPage] = useState({ items: [], total: 0, page: 1, per_page: 10, pages: 1 });
  const [hojaDetalle, setHojaDetalle] = useState(null);
  const [hojaDetalleOpen, setHojaDetalleOpen] = useState(false);
  const [detalleVerSinFirma, setDetalleVerSinFirma] = useState(false);
  const [detalleMostrarVisitadas, setDetalleMostrarVisitadas] = useState(false);

  const [visitDialogOpen, setVisitDialogOpen] = useState(false);
  const [selectedStop, setSelectedStop] = useState(null);
  const [visitPayload, setVisitPayload] = useState({ visitado: true, documento_firmado: true, observacion: '' });

  const [historyOpen, setHistoryOpen] = useState(false);
  const [historyData, setHistoryData] = useState(null);
  const [savingPerfil, setSavingPerfil] = useState(false);

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

  const loadLotes = async () => {
    const now = new Date();
    const data = await api.getConsultorLotes(token, `anio=${now.getFullYear()}&mes=${now.getMonth() + 1}`);
    setLotes(data);
    if (!consultorLoteId && data[0]) {
      setConsultorLoteId(String(data[0].id));
    }
  };

  const loadHojas = async (loteId, page = 1, perPage = 10) => {
    if (!loteId) return;
    const data = await api.getHojasRuta(token, `consultor_lote_id=${loteId}&page=${page}&per_page=${perPage}`);
    setHojasPage(data);
  };

  const loadAll = async () => {
    setLoading(true);
    setError('');
    try {
      await Promise.all([loadPerfil(), loadLotes()]);
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
    if (!consultorLoteId) return;
    loadHojas(consultorLoteId, 1, hojasPage.per_page).catch((err) => setError(err.message));
  }, [consultorLoteId]);

  const generarPlanDia = async () => {
    if (!consultorLoteId) return;
    setError('');
    try {
      const data = await api.planificarLoteDia(token, consultorLoteId, {
        punto_partida_direccion: perfil.punto_partida_direccion,
        punto_retorno_direccion: perfil.punto_retorno_direccion || perfil.punto_partida_direccion,
        movilidad: perfil.movilidad,
        disponibilidad_minutos: Number(perfil.disponibilidad_minutos),
        punto_partida_latitud: perfil.punto_partida_latitud || undefined,
        punto_partida_longitud: perfil.punto_partida_longitud || undefined,
        punto_retorno_latitud: perfil.punto_retorno_latitud || undefined,
        punto_retorno_longitud: perfil.punto_retorno_longitud || undefined,
        consider_operational_time: considerOperationalTime,
        operational_margin_minutes: Number(operationalMarginMinutes) || 0,
      });
      setPlanDia(data.plan);
      if (!tituloHoja) {
        setTituloHoja(`Hoja ${new Date().toLocaleString('es-AR')}`);
      }
      const nExcl = Number(data.excluidas_por_hojas_previas) || 0;
      if (nExcl > 0) {
        setNotice(
          `Propuesta generada. Se dejaron fuera ${nExcl} dirección${nExcl === 1 ? '' : 'es'} que ya están en otra(s) hoja(s) de este lote.`
        );
      } else {
        setNotice('Propuesta generada desde lote mensual.');
      }
    } catch (err) {
      setError(err.message);
    }
  };

  const guardarHoja = async () => {
    if (!consultorLoteId || !planDia) return;
    try {
      await api.guardarHojaRutaDesdeLote(token, consultorLoteId, {
        titulo: tituloHoja || undefined,
        estado: 'validada',
        constraints: {
          movilidad: perfil.movilidad,
          disponibilidad_minutos: Number(perfil.disponibilidad_minutos),
          consider_operational_time: considerOperationalTime,
          operational_margin_minutes: Number(operationalMarginMinutes) || 0,
          punto_partida_direccion: perfil.punto_partida_direccion,
          punto_retorno_direccion: perfil.punto_retorno_direccion || perfil.punto_partida_direccion,
        },
        plan: planDia,
      });
      await loadHojas(consultorLoteId, 1, hojasPage.per_page);
      setNotice('Hoja guardada. Ya puedes gestionarla en bloques de 10.');
      window.location.hash = '#consultor-hojas-ruta';
    } catch (err) {
      setError(err.message);
    }
  };

  const guardarPerfil = async () => {
    setError('');
    setNotice('');
    setSavingPerfil(true);
    try {
      await api.updateConsultorPerfil(token, {
        punto_partida_direccion: perfil.punto_partida_direccion || '',
        punto_retorno_direccion: perfil.punto_retorno_direccion || perfil.punto_partida_direccion || '',
        movilidad: perfil.movilidad,
        disponibilidad_minutos: Number(perfil.disponibilidad_minutos) || 240,
        punto_partida_latitud: perfil.punto_partida_latitud === '' ? null : Number(perfil.punto_partida_latitud),
        punto_partida_longitud: perfil.punto_partida_longitud === '' ? null : Number(perfil.punto_partida_longitud),
        punto_retorno_latitud: perfil.punto_retorno_latitud === '' ? null : Number(perfil.punto_retorno_latitud),
        punto_retorno_longitud: perfil.punto_retorno_longitud === '' ? null : Number(perfil.punto_retorno_longitud),
      });
      await loadPerfil();
      setNotice('Perfil operativo guardado.');
    } catch (err) {
      setError(err.message);
    } finally {
      setSavingPerfil(false);
    }
  };

  const abrirDetalleHoja = async (id) => {
    try {
      const data = await api.getHojaRutaById(token, id);
      setHojaDetalle(data);
      setDetalleVerSinFirma(false);
      setDetalleMostrarVisitadas(false);
      setHojaDetalleOpen(true);
    } catch (err) {
      setError(err.message);
    }
  };

  const cargarProximas10 = async () => {
    if (!hojaDetalle?.id) return;
    try {
      const data = await api.openNextBatchHojaRuta(token, hojaDetalle.id, { limit: 10 });
      if (data.google_maps_url) {
        window.open(data.google_maps_url, '_blank', 'noopener,noreferrer');
      }
      const n = data.batch?.length ?? 0;
      setNotice(data.has_more ? `Bloque de ${n} parada(s) cargado en Maps.` : `Ultimo bloque (${n} parada(s)) cargado.`);
      const refreshed = await api.getHojaRutaById(token, hojaDetalle.id);
      setHojaDetalle(refreshed);
    } catch (err) {
      setError(err.message);
    }
  };

  useEffect(() => {
    if (!hojaDetalle?.plan?.stops) return;
    const hayPend = hojaDetalle.plan.stops.some((s) => !s.visita || s.visita.visitado !== true);
    if (hayPend) {
      setDetalleVerSinFirma(false);
    }
  }, [hojaDetalle]);

  const mapsBloqueInfo = useMemo(() => {
    const total = (hojaDetalle?.plan?.stops || []).length;
    const nextOff = Number(hojaDetalle?.next_offset ?? 0);
    const rawStart = hojaDetalle?.last_opened_batch_start;
    const lastStart = rawStart === null || rawStart === undefined || rawStart === '' ? null : Number(rawStart);
    const ultimoTramo = lastStart !== null && !Number.isNaN(lastStart) && nextOff > lastStart;
    const pendientesEnMaps = Math.max(0, total - nextOff);
    return {
      total,
      nextOff,
      pendientesEnMaps,
      ultimoTramo,
      desde: ultimoTramo ? lastStart + 1 : null,
      hasta: ultimoTramo ? nextOff : null,
      esVistaPreviaMaps: !ultimoTramo && nextOff === 0,
    };
  }, [hojaDetalle]);

  const listadoDetalle = useMemo(() => {
    const stopsRaw = hojaDetalle?.plan?.stops || [];
    const withOrd = stopsRaw.map((s, i) => ({ ...s, _ordenGlobal: i + 1 }));
    const pendientes = withOrd.filter((s) => !s.visita || s.visita.visitado !== true);
    const sinFirma = withOrd.filter(
      (s) => s.visita && s.visita.visitado === true && s.visita.documento_firmado !== true,
    );
    const visitadas = withOrd.filter((s) => s.visita && s.visita.visitado === true);
    const visitadasSorted = [...visitadas].sort((a, b) => {
      const af = a.visita.documento_firmado === true ? 1 : 0;
      const bf = b.visita.documento_firmado === true ? 1 : 0;
      if (af !== bf) return af - bf;
      return a._ordenGlobal - b._ordenGlobal;
    });
    const hayPendientes = pendientes.length > 0;
    const haySinFirma = sinFirma.length > 0;
    const hayVisitadas = visitadas.length > 0;

    let rows;
    let modo;
    if (detalleMostrarVisitadas) {
      rows = visitadasSorted;
      modo = 'visitadas';
    } else if (!hayPendientes && detalleVerSinFirma) {
      rows = sinFirma;
      modo = 'sin_firma';
    } else {
      rows = pendientes.slice(0, 10);
      modo = 'pendientes';
    }
    return {
      rows,
      modo,
      pendientesTotal: pendientes.length,
      sinFirmaTotal: sinFirma.length,
      visitadasTotal: visitadas.length,
      hayPendientes,
      haySinFirma,
      hayVisitadas,
      pendientesFueraDeTabla: modo === 'pendientes' ? Math.max(0, pendientes.length - rows.length) : 0,
    };
  }, [hojaDetalle, detalleVerSinFirma, detalleMostrarVisitadas]);

  const openVisitDialog = (stop) => {
    setSelectedStop(stop);
    const v = stop.visita;
    const visitado = v && v.visitado === true;
    const firmado = v && v.documento_firmado === true;
    setVisitPayload({
      visitado: true,
      documento_firmado: visitado && !firmado ? false : true,
      observacion: '',
    });
    setVisitDialogOpen(true);
  };

  const guardarVisita = async () => {
    if (!hojaDetalle?.id || !selectedStop?.domicilio_id) return;
    try {
      await api.registrarVisitaHojaRuta(token, hojaDetalle.id, {
        domicilio_id: selectedStop.domicilio_id,
        visitado: Boolean(visitPayload.visitado),
        documento_firmado: Boolean(visitPayload.documento_firmado),
        observacion: visitPayload.observacion,
      });
      setVisitDialogOpen(false);
      setNotice('Visita registrada. Listado actualizado.');
      const refreshed = await api.getHojaRutaById(token, hojaDetalle.id);
      setHojaDetalle(refreshed);
      if (consultorLoteId) {
        await loadHojas(consultorLoteId, hojasPage.page, hojasPage.per_page);
      }
    } catch (err) {
      setError(err.message);
    }
  };

  const openHistory = async (domicilioId) => {
    try {
      const data = await api.getHistorialDomicilio(token, domicilioId);
      setHistoryData(data);
      setHistoryOpen(true);
    } catch (err) {
      setError(err.message);
    }
  };

  const mapPoints = useMemo(() => {
    if (!planDia) return [];
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
      id: `p-${s.domicilio_id || s.asignacion_id || idx}`,
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
  }, [planDia]);

  if (loading) {
    return (
      <Stack spacing={1.5} alignItems="center" py={6}>
        <CircularProgress />
        <Typography>Cargando lotes mensuales...</Typography>
      </Stack>
    );
  }

  return (
    <Stack spacing={2.5}>
      <Box>
        <Typography variant={isCompact ? 'h6' : 'h5'} component="h1">
          Panel Consultor
        </Typography>
        <Typography color="text.secondary" variant="body2" sx={{ mt: 0.5 }}>
          Planificacion diaria sobre lotes mensuales y ejecucion en bloques de 10.
        </Typography>
      </Box>

      {error ? <Alert severity="error" onClose={() => setError('')}>{error}</Alert> : null}
      {notice ? <Alert severity="success" onClose={() => setNotice('')}>{notice}</Alert> : null}

      <Card>
        <CardContent>
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1.5} alignItems={{ sm: 'center' }}>
            <TextField
              select
              label="Lote mensual asignado"
              value={consultorLoteId}
              onChange={(e) => setConsultorLoteId(e.target.value)}
              fullWidth
              sx={{ flex: { sm: 1 }, minWidth: { sm: 260 }, maxWidth: { md: 520 } }}
            >
              {lotes.map((l) => (
                <MenuItem key={l.id} value={String(l.id)}>
                  Lote #{l.numero_lote} - {l.titulo} ({l.total_domicilios} domicilios)
                </MenuItem>
              ))}
            </TextField>
            <Button variant="outlined" onClick={loadAll} fullWidth={isCompact} sx={{ flexShrink: 0 }}>
              Refrescar lotes
            </Button>
          </Stack>
        </CardContent>
      </Card>

      {currentView === 'consultor-resumen' ? (
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 12, sm: 4 }}>
            <Card variant="outlined">
              <CardContent sx={{ py: 2 }}>
                <Typography variant="subtitle2" color="text.secondary">
                  Lotes del mes
                </Typography>
                <Typography variant="h5" component="p">
                  {lotes.length}
                </Typography>
              </CardContent>
            </Card>
          </Grid2>
          <Grid2 size={{ xs: 12, sm: 4 }}>
            <Card variant="outlined">
              <CardContent sx={{ py: 2 }}>
                <Typography variant="subtitle2" color="text.secondary">
                  Hojas guardadas
                </Typography>
                <Typography variant="h5" component="p">
                  {hojasPage.total}
                </Typography>
              </CardContent>
            </Card>
          </Grid2>
          <Grid2 size={{ xs: 12, sm: 4 }}>
            <Card variant="outlined">
              <CardContent sx={{ py: 2 }}>
                <Typography variant="subtitle2" color="text.secondary">
                  Paradas propuestas
                </Typography>
                <Typography variant="h5" component="p">
                  {planDia?.resumen?.total_planificadas ?? 0}
                </Typography>
              </CardContent>
            </Card>
          </Grid2>
        </Grid2>
      ) : null}

      {currentView === 'consultor-plan-dia' ? (
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 12, lg: 4 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" mb={1.5}>Constructor diario desde lote</Typography>
                <Stack spacing={1.2}>
                  <TextField label="Punto de partida" value={perfil.punto_partida_direccion} onChange={(e) => setPerfil((p) => ({ ...p, punto_partida_direccion: e.target.value }))} />
                  <TextField label="Punto de retorno" value={perfil.punto_retorno_direccion} onChange={(e) => setPerfil((p) => ({ ...p, punto_retorno_direccion: e.target.value }))} />
                  <TextField select label="Movilidad" value={perfil.movilidad} onChange={(e) => setPerfil((p) => ({ ...p, movilidad: e.target.value }))}>
                    <MenuItem value="a_pie">A pie</MenuItem>
                    <MenuItem value="bicicleta">Bicicleta</MenuItem>
                    <MenuItem value="autobus">Autobus</MenuItem>
                    <MenuItem value="vehiculo">Vehiculo</MenuItem>
                  </TextField>
                  <TextField label="Disponibilidad (min)" type="number" value={perfil.disponibilidad_minutos} onChange={(e) => setPerfil((p) => ({ ...p, disponibilidad_minutos: Number(e.target.value) }))} />
                  <FormControlLabel control={<Switch checked={considerOperationalTime} onChange={(e) => setConsiderOperationalTime(e.target.checked)} />} label="Considerar tiempo operativo" />
                  <TextField
                    label="Margen tolerado (min)"
                    type="number"
                    value={operationalMarginMinutes}
                    onChange={(e) => setOperationalMarginMinutes(Math.max(0, Number(e.target.value) || 0))}
                    disabled={!considerOperationalTime}
                  />
                  <TextField label="Titulo hoja" value={tituloHoja} onChange={(e) => setTituloHoja(e.target.value)} />
                  <Button variant="outlined" onClick={guardarPerfil} disabled={savingPerfil}>
                    {savingPerfil ? 'Guardando perfil...' : 'Guardar perfil'}
                  </Button>
                  <Stack direction={{ xs: 'column', sm: 'row' }} spacing={1}>
                    <Button variant="contained" onClick={generarPlanDia} fullWidth={isCompact}>
                      Generar propuesta
                    </Button>
                    <Button
                      disabled={!planDia}
                      variant="contained"
                      color="secondary"
                      startIcon={<SaveOutlinedIcon />}
                      onClick={guardarHoja}
                      fullWidth={isCompact}
                    >
                      Guardar hoja
                    </Button>
                  </Stack>
                </Stack>
              </CardContent>
            </Card>
          </Grid2>
          <Grid2 size={{ xs: 12, lg: 8 }}>
            <Card>
              <CardContent>
                <Typography variant="h6">Propuesta</Typography>
                {planDia ? (
                  <Stack spacing={1}>
                    <Typography variant="body2" color="text.secondary">
                      Planificadas: {planDia.resumen.total_planificadas}/{planDia.resumen.total_pendientes} · Distancia total: {planDia.resumen.distancia_km} km
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      Logistica: {planDia.resumen.minutos_logistica} min · Operativo: {planDia.resumen.minutos_operativos} min · Total c/operativa: {planDia.resumen.minutos_totales_con_operativa} min
                    </Typography>
                    {planDia.ruta?.google_maps_url ? (
                      <Button size="small" variant="outlined" startIcon={<OpenInNewOutlinedIcon />} onClick={() => window.open(planDia.ruta.google_maps_url, '_blank', 'noopener,noreferrer')}>
                        Abrir ruta completa en Google Maps
                      </Button>
                    ) : null}
                    <TableContainer sx={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch', maxWidth: '100%' }}>
                      <Table size="small" sx={{ minWidth: 520 }}>
                        <TableHead>
                          <TableRow>
                            <TableCell>#</TableCell>
                            <TableCell>Domicilio</TableCell>
                            <TableCell>Servicio</TableCell>
                            <TableCell align="right">Dist. (km)</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {(planDia.stops || []).map((s, idx) => (
                            <TableRow key={`st-${s.domicilio_id}-${idx}`}>
                              <TableCell>{idx + 1}</TableCell>
                              <TableCell sx={{ whiteSpace: 'nowrap' }}>
                                {s.calle} {s.altura}
                              </TableCell>
                              <TableCell>{s.servicio}</TableCell>
                              <TableCell align="right">{Number(s.explanation?.distance_km || 0).toFixed(2)}</TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  </Stack>
                ) : <Alert severity="info">Genera una propuesta para visualizarla.</Alert>}
              </CardContent>
            </Card>
          </Grid2>
        </Grid2>
      ) : null}

      {currentView === 'consultor-hojas-ruta' ? (
        <Card>
          <CardContent>
            <Stack direction={{ xs: 'column', sm: 'row' }} justifyContent="space-between" alignItems={{ xs: 'stretch', sm: 'center' }} spacing={1} mb={1.5}>
              <Typography variant="h6">Hojas de ruta</Typography>
              <Stack direction="row" spacing={1} justifyContent={{ xs: 'space-between', sm: 'flex-end' }}>
                <Button size="small" disabled={hojasPage.page <= 1} onClick={() => loadHojas(consultorLoteId, hojasPage.page - 1, hojasPage.per_page)}>
                  Anterior
                </Button>
                <Button size="small" disabled={hojasPage.page >= hojasPage.pages} onClick={() => loadHojas(consultorLoteId, hojasPage.page + 1, hojasPage.per_page)}>
                  Siguiente
                </Button>
              </Stack>
            </Stack>
            <TableContainer sx={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch', maxWidth: '100%' }}>
              <Table size="small" sx={{ minWidth: 720 }}>
                <TableHead>
                  <TableRow>
                    <TableCell>ID</TableCell>
                    <TableCell>Titulo</TableCell>
                    <TableCell>Estado</TableCell>
                    <TableCell sx={{ minWidth: 140 }}>Resumen</TableCell>
                    <TableCell align="right">Acciones</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {hojasPage.items.map((h) => (
                    <TableRow key={h.id} hover>
                      <TableCell>{h.id}</TableCell>
                      <TableCell sx={{ maxWidth: 200, wordBreak: 'break-word' }}>{h.titulo}</TableCell>
                      <TableCell>{h.estado}</TableCell>
                      <TableCell sx={{ whiteSpace: { xs: 'nowrap', md: 'normal' } }}>{h.status_summary?.texto || '-'}</TableCell>
                      <TableCell align="right">
                        <Button size="small" startIcon={<VisibilityOutlinedIcon />} onClick={() => abrirDetalleHoja(h.id)}>
                          Detalle
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </CardContent>
        </Card>
      ) : null}

      <Card>
        <CardContent>
          <Typography variant="h6" mb={1}>Mapa</Typography>
          <RouteMap points={mapPoints} height={isCompact ? 260 : 360} />
        </CardContent>
      </Card>

      <Dialog
        open={hojaDetalleOpen}
        onClose={() => setHojaDetalleOpen(false)}
        maxWidth="md"
        fullWidth
        fullScreen={dialogFullScreen}
        scroll="paper"
      >
        <DialogTitle>Detalle hoja y gestion por bloques (max. 10 en Maps)</DialogTitle>
        <DialogContent>
          {hojaDetalle ? (
            <Stack spacing={1.2}>
              <Typography variant="body2"><strong>Titulo:</strong> {hojaDetalle.titulo}</Typography>
              <Typography variant="body2"><strong>Estado:</strong> {hojaDetalle.estado}</Typography>
              <Alert severity="info">
                En la hoja hay <strong>{mapsBloqueInfo.total}</strong> paradas. Google Maps en celular admite ~10 destinos por ruta; usá el botón para avanzar.
                {mapsBloqueInfo.ultimoTramo ? (
                  <>
                    {' '}Último tramo enviado a Maps: orden <strong>{mapsBloqueInfo.desde}</strong>–<strong>{mapsBloqueInfo.hasta}</strong>.
                  </>
                ) : mapsBloqueInfo.esVistaPreviaMaps ? (
                  <> Todavía no cargaste un bloque en Maps.</>
                ) : null}
                {mapsBloqueInfo.pendientesEnMaps > 0 ? (
                  <> Faltan <strong>{mapsBloqueInfo.pendientesEnMaps}</strong> parada(s) por incluir en Maps con el botón.</>
                ) : mapsBloqueInfo.total > 0 && mapsBloqueInfo.nextOff >= mapsBloqueInfo.total ? (
                  <> Toda la ruta ya fue pasada a Maps (según el puntero actual).</>
                ) : null}
              </Alert>
              <Alert
                severity={
                  listadoDetalle.modo === 'pendientes'
                    ? 'info'
                    : listadoDetalle.modo === 'visitadas'
                      ? 'success'
                      : 'warning'
                }
              >
                {listadoDetalle.modo === 'pendientes' ? (
                  <>
                    Primera recorrida: solo se listan paradas con <strong>Visitado = no</strong>. Pendientes:{' '}
                    <strong>{listadoDetalle.pendientesTotal}</strong>. La tabla muestra hasta 10; al guardar una visita se
                    actualiza al instante y entra la siguiente de la ruta.
                    {listadoDetalle.pendientesFueraDeTabla > 0 ? (
                      <>
                        {' '}
                        Fuera de esta vista quedan <strong>{listadoDetalle.pendientesFueraDeTabla}</strong> sin visitar (se
                        irán mostrando al completar las de arriba).
                      </>
                    ) : null}
                    {!listadoDetalle.hayPendientes && listadoDetalle.haySinFirma && !detalleVerSinFirma && !detalleMostrarVisitadas ? (
                      <> Recorrida de visitas completa. Podés usar los interruptores de abajo para revisar firmas o todas las visitadas.</>
                    ) : null}
                  </>
                ) : listadoDetalle.modo === 'visitadas' ? (
                  <>
                    <strong>Visitadas</strong> en esta hoja: <strong>{listadoDetalle.visitadasTotal}</strong>. Orden: primero
                    las que faltan <strong>documento firmado</strong>, luego las ya firmadas (conservando orden de hoja dentro
                    de cada grupo).
                  </>
                ) : (
                  <>
                    Modo seguimiento: visitados <strong>sin</strong> documento firmado. Total:{' '}
                    <strong>{listadoDetalle.sinFirmaTotal}</strong>.
                  </>
                )}
              </Alert>
              <FormControlLabel
                control={(
                  <Switch
                    checked={detalleMostrarVisitadas}
                    onChange={(e) => {
                      const on = e.target.checked;
                      setDetalleMostrarVisitadas(on);
                      if (on) setDetalleVerSinFirma(false);
                    }}
                    disabled={!listadoDetalle.hayVisitadas}
                  />
                )}
                label="Mostrar visitadas"
              />
              {!listadoDetalle.hayPendientes ? (
                <FormControlLabel
                  control={(
                    <Switch
                      checked={detalleVerSinFirma}
                      onChange={(e) => {
                        const on = e.target.checked;
                        setDetalleVerSinFirma(on);
                        if (on) setDetalleMostrarVisitadas(false);
                      }}
                      disabled={!listadoDetalle.haySinFirma || detalleMostrarVisitadas}
                    />
                  )}
                  label="Listar visitados sin documento firmado"
                />
              ) : null}
              {!listadoDetalle.hayPendientes && !listadoDetalle.haySinFirma ? (
                <Typography variant="body2" color="success.main">Todas las paradas están visitadas y con documento firmado.</Typography>
              ) : null}
              <Button variant="contained" onClick={cargarProximas10} fullWidth={dialogFullScreen} sx={{ alignSelf: 'flex-start' }}>
                Cargar proximas 10 y abrir Maps
              </Button>
              <TableContainer sx={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch', maxWidth: '100%' }}>
                <Table size="small" sx={{ minWidth: dialogFullScreen ? 560 : 640 }}>
                  <TableHead>
                    <TableRow>
                      <TableCell>#</TableCell>
                      <TableCell>Domicilio</TableCell>
                      <TableCell>Servicio</TableCell>
                      <TableCell>Estado</TableCell>
                      <TableCell align="right">Acciones</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {listadoDetalle.rows.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={5}>
                          <Typography variant="body2" color="text.secondary">
                            {listadoDetalle.modo === 'pendientes'
                              ? 'No hay paradas pendientes de visitar.'
                              : listadoDetalle.modo === 'visitadas'
                                ? 'No hay paradas marcadas como visitadas todavía.'
                                : 'No hay visitas pendientes de firma.'}
                          </Typography>
                        </TableCell>
                      </TableRow>
                    ) : (
                      listadoDetalle.rows.map((s) => (
                        <TableRow key={`dt-${s.domicilio_id}-${s._ordenGlobal}`}>
                          <TableCell>{s._ordenGlobal}</TableCell>
                          <TableCell sx={{ whiteSpace: 'nowrap' }}>
                            {s.calle} {s.altura}
                          </TableCell>
                          <TableCell>{s.servicio}</TableCell>
                          <TableCell>
                            {listadoDetalle.modo === 'pendientes' ? (
                              <Chip size="small" label="Por visitar" color="default" variant="outlined" />
                            ) : listadoDetalle.modo === 'sin_firma' ? (
                              <Chip size="small" label="Sin firma" color="warning" variant="outlined" />
                            ) : s.visita?.documento_firmado === true ? (
                              <Chip size="small" label="Firmado" color="success" variant="outlined" />
                            ) : (
                              <Chip size="small" label="Sin firma" color="warning" variant="outlined" />
                            )}
                          </TableCell>
                          <TableCell align="right">
                            <Stack direction={{ xs: 'column', sm: 'row' }} spacing={0.5} alignItems={{ xs: 'stretch', sm: 'flex-end' }}>
                              <Button size="small" onClick={() => openVisitDialog(s)}>
                                Visita
                              </Button>
                              <Button size="small" startIcon={<HistoryOutlinedIcon />} onClick={() => openHistory(s.domicilio_id)}>
                                Historial
                              </Button>
                            </Stack>
                          </TableCell>
                        </TableRow>
                      ))
                    )}
                  </TableBody>
                </Table>
              </TableContainer>
            </Stack>
          ) : null}
        </DialogContent>
        <DialogActions><Button onClick={() => setHojaDetalleOpen(false)}>Cerrar</Button></DialogActions>
      </Dialog>

      <Dialog open={visitDialogOpen} onClose={() => setVisitDialogOpen(false)} maxWidth="sm" fullWidth fullScreen={dialogFullScreen}>
        <DialogTitle>Registrar visita</DialogTitle>
        <DialogContent>
          <Stack spacing={1.2} mt={0.5}>
            <FormControlLabel control={<Switch checked={Boolean(visitPayload.visitado)} onChange={(e) => setVisitPayload((p) => ({ ...p, visitado: e.target.checked }))} />} label="Visitado" />
            <FormControlLabel control={<Switch checked={Boolean(visitPayload.documento_firmado)} onChange={(e) => setVisitPayload((p) => ({ ...p, documento_firmado: e.target.checked }))} />} label="Documento firmado" />
            <TextField label="Observacion" value={visitPayload.observacion} onChange={(e) => setVisitPayload((p) => ({ ...p, observacion: e.target.value }))} inputProps={{ maxLength: 255 }} />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setVisitDialogOpen(false)}>Cancelar</Button>
          <Button variant="contained" onClick={guardarVisita}>Guardar</Button>
        </DialogActions>
      </Dialog>

      <Dialog open={historyOpen} onClose={() => setHistoryOpen(false)} maxWidth="md" fullWidth fullScreen={dialogFullScreen} scroll="paper">
        <DialogTitle>Historial por domicilio</DialogTitle>
        <DialogContent>
          {historyData ? (
            <Stack spacing={1}>
              <Typography variant="body2">
                <strong>Domicilio:</strong> {historyData.domicilio?.calle} {historyData.domicilio?.altura}
              </Typography>
              <TableContainer sx={{ overflowX: 'auto', WebkitOverflowScrolling: 'touch' }}>
                <Table size="small" sx={{ minWidth: 520 }}>
                  <TableHead>
                    <TableRow>
                      <TableCell>Hoja</TableCell>
                      <TableCell>Visitado</TableCell>
                      <TableCell>Firmado</TableCell>
                      <TableCell>Observacion</TableCell>
                      <TableCell>Fecha</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {(historyData.historial || []).map((h) => (
                      <TableRow key={`${h.id}-${h.observacion_id || 0}`}>
                        <TableCell sx={{ maxWidth: 140, wordBreak: 'break-word' }}>{h.hoja_titulo || `#${h.hoja_ruta_id}`}</TableCell>
                        <TableCell>{Number(h.visitado) === 1 ? 'Si' : 'No'}</TableCell>
                        <TableCell>{Number(h.documento_firmado) === 1 ? 'Si' : 'No'}</TableCell>
                        <TableCell sx={{ maxWidth: 200, wordBreak: 'break-word' }}>{h.observacion || '-'}</TableCell>
                        <TableCell sx={{ whiteSpace: 'nowrap' }}>
                          {(h.observacion_created_at || h.updated_at || '').toString().slice(0, 19).replace('T', ' ')}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            </Stack>
          ) : null}
        </DialogContent>
        <DialogActions><Button onClick={() => setHistoryOpen(false)}>Cerrar</Button></DialogActions>
      </Dialog>
    </Stack>
  );
}
