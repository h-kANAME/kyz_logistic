import DownloadOutlinedIcon from '@mui/icons-material/DownloadOutlined';
import ContentCopyOutlinedIcon from '@mui/icons-material/ContentCopyOutlined';
import UploadFileOutlinedIcon from '@mui/icons-material/UploadFileOutlined';
import VisibilityOutlinedIcon from '@mui/icons-material/VisibilityOutlined';
import AltRouteOutlinedIcon from '@mui/icons-material/AltRouteOutlined';
import EditOutlinedIcon from '@mui/icons-material/EditOutlined';
import DeleteOutlineOutlinedIcon from '@mui/icons-material/DeleteOutlineOutlined';
import KeyOutlinedIcon from '@mui/icons-material/KeyOutlined';
import PersonOffOutlinedIcon from '@mui/icons-material/PersonOffOutlined';
import PsychologyAltOutlinedIcon from '@mui/icons-material/PsychologyAltOutlined';
import MoreHorizOutlinedIcon from '@mui/icons-material/MoreHorizOutlined';
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
  Grid2,
  IconButton,
  Menu,
  MenuItem,
  TableContainer,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableRow,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import dayjs from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import { RouteMap } from '../../components/RouteMap';
import { StatCard } from '../../components/StatCard';
import { api } from '../../services/api';

const USER_ROLES = ['admin', 'supervisor', 'consultor'];
const JORNADA_ESTADOS = ['borrador', 'activa', 'completada', 'cancelada'];
const ADMIN_VIEWS = ['admin-home', 'admin-domicilios', 'admin-usuarios', 'admin-secciones', 'admin-lotes', 'admin-jornadas'];

export function AdminDashboard({ token }) {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [usuarios, setUsuarios] = useState([]);
  const [secciones, setSecciones] = useState([]);
  const [domicilios, setDomicilios] = useState({ items: [], total: 0 });
  const [consultores, setConsultores] = useState([]);
  const [jornadas, setJornadas] = useState([]);
  const [lotes, setLotes] = useState([]);

  const [newUser, setNewUser] = useState({ nombre: '', email: '', password: '', rol: 'consultor' });
  const [newJornada, setNewJornada] = useState({ consultor_id: '', fecha: dayjs().format('YYYY-MM-DD') });
  const [savingUser, setSavingUser] = useState(false);
  const [savingEdit, setSavingEdit] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [importFile, setImportFile] = useState(null);
  const [importing, setImporting] = useState(false);
  const [notice, setNotice] = useState('');
  const [editOpen, setEditOpen] = useState(false);
  const [passwordOpen, setPasswordOpen] = useState(false);
  const [selectedUser, setSelectedUser] = useState(null);
  const [editUser, setEditUser] = useState({ nombre: '', email: '', rol: 'consultor', activo: 1 });
  const [newPassword, setNewPassword] = useState('');
  const [sectionEditOpen, setSectionEditOpen] = useState(false);
  const [selectedSection, setSelectedSection] = useState(null);
  const [sectionDescription, setSectionDescription] = useState('');
  const [savingSection, setSavingSection] = useState(false);
  const [selectedJornada, setSelectedJornada] = useState(null);
  const [asignaciones, setAsignaciones] = useState([]);
  const [currentView, setCurrentView] = useState('admin-home');
  const [createJornadaOpen, setCreateJornadaOpen] = useState(false);
  const [editJornadaOpen, setEditJornadaOpen] = useState(false);
  const [confirmEditJornadaOpen, setConfirmEditJornadaOpen] = useState(false);
  const [confirmDeleteJornadaOpen, setConfirmDeleteJornadaOpen] = useState(false);
  const [editingJornada, setEditingJornada] = useState(null);
  const [jornadaDraft, setJornadaDraft] = useState({ consultor_id: '', fecha: dayjs().format('YYYY-MM-DD') });
  const [deletingJornada, setDeletingJornada] = useState(null);
  const [savingJornadaEdit, setSavingJornadaEdit] = useState(false);
  const [deletingJornadaBusy, setDeletingJornadaBusy] = useState(false);
  const [estadoMenuAnchorEl, setEstadoMenuAnchorEl] = useState(null);
  const [estadoMenuJornadaId, setEstadoMenuJornadaId] = useState(null);
  const [geocodingEstado, setGeocodingEstado] = useState({ total: 0, geocodificados: 0, pendientes: 0, cobertura_pct: 0 });
  const [wizardPage, setWizardPage] = useState({ items: [], total: 0, page: 1, per_page: 10, pages: 1, only_pending: true });
  const [wizardBusy, setWizardBusy] = useState(false);
  const [wizardSelected, setWizardSelected] = useState(null);
  const [wizardManual, setWizardManual] = useState({ latitud: '', longitud: '' });
  const [wizardQuery, setWizardQuery] = useState('');
  const [wizardAttempts, setWizardAttempts] = useState([]);
  const [wizardBulkRows, setWizardBulkRows] = useState([]);
  const [loteDraft, setLoteDraft] = useState({
    anio: dayjs().year(),
    mes: dayjs().month() + 1,
    numero_lote: 1,
    titulo: '',
    observacion: '',
  });
  const [selectedLoteId, setSelectedLoteId] = useState('');
  const [assignConsultorId, setAssignConsultorId] = useState('');

  const buildWizardQuery = (page = 1, onlyPending = true, loteId = selectedLoteId) => {
    const query = [`page=${page}`, `per_page=${wizardPage.per_page}`, `only_pending=${onlyPending ? 1 : 0}`];
    if (loteId) {
      query.push(`lote_id=${encodeURIComponent(loteId)}`);
    }
    return query.join('&');
  };

  const loadData = async () => {
    setLoading(true);
    setError('');
    try {
      const currentYear = dayjs().year();
      const currentMonth = dayjs().month() + 1;
      const [usuariosData, seccionesData, domiciliosData, consultoresData, jornadasData, lotesData] = await Promise.all([
        api.getUsuarios(token),
        api.getSecciones(token),
        api.getDomicilios(token, 'page=1&per_page=25'),
        api.getUsuarios(token, 'consultor'),
        api.getJornadas(token),
        api.getLotes(token, `anio=${currentYear}&mes=${currentMonth}`),
      ]);
      setUsuarios(usuariosData);
      setSecciones(seccionesData);
      setDomicilios(domiciliosData);
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

  const refreshGeocodingAudit = async (query = '') => {
    const loteId = selectedLoteId ? Number(selectedLoteId) : null;
    const [geoEstadoData, wizardData] = await Promise.all([
      api.getGeocodingEstado(token, loteId),
      api.geocodingWizardQueue(token, query || buildWizardQuery(wizardPage.page, wizardPage.only_pending)),
    ]);
    setGeocodingEstado(geoEstadoData);
    setWizardPage(wizardData);
  };

  const limpiarGeocodificacion = async () => {
    const ok = window.confirm('Esto limpiará latitud/longitud/geocodificado. Queres continuar?');
    if (!ok) {
      return;
    }
    setWizardBusy(true);
    try {
      await api.geocodingReset(token, { only_geocoded: false });
      setNotice('Geocodificacion limpiada. Inicia el wizard para normalizar y completar.');
      await refreshGeocodingAudit(buildWizardQuery(1, true));
    } catch (err) {
      setError(err.message);
    } finally {
      setWizardBusy(false);
    }
  };

  const cargarWizardPage = async (page = 1, onlyPending = wizardPage.only_pending) => {
    setWizardBusy(true);
    try {
      await refreshGeocodingAudit(buildWizardQuery(page, onlyPending));
    } catch (err) {
      setError(err.message);
    } finally {
      setWizardBusy(false);
    }
  };

  const geocodingMasivo = async (provider = 'nominatim') => {
    const providerSafe = provider === 'google' ? 'google' : 'nominatim';
    if (currentView !== 'admin-home') {
      window.location.hash = '#admin-home';
      setCurrentView('admin-home');
    }
    const currentItems = (wizardPage.items || []).slice(0, 10);
    if (!currentItems.length) {
      setNotice('No hay domicilios en la pagina actual para geocoding masivo.');
      return;
    }

    setWizardBusy(true);
    try {
      const result = await api.geocodingWizardBulkPropose(token, {
        domicilio_ids: currentItems.map((item) => item.id),
      }, providerSafe);
      const rows = (result.items || []).map((item) => ({
        id: item.id,
        calle: item.calle,
        altura: item.altura,
        seccion_numero: item.seccion_numero,
        latitud: item.proposed_latitud ?? '',
        longitud: item.proposed_longitud ?? '',
        matched: Boolean(item.matched),
        original_latitud: item.proposed_latitud ?? '',
        original_longitud: item.proposed_longitud ?? '',
        edited: false,
      }));
      setWizardBulkRows(rows);
      if (rows.length === 0) {
        setNotice(`Propuestas masivas con ${providerSafe} completadas, pero sin filas en el lote visible.`);
      } else {
        setNotice(`Propuestas masivas generadas con ${providerSafe}: ${rows.length} filas listas para validar y guardar.`);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setWizardBusy(false);
    }
  };

  const updateWizardBulkRow = (id, field, value) => {
    setWizardBulkRows((prev) => prev.map((row) => {
      if (row.id !== id) {
        return row;
      }
      const next = { ...row, [field]: value };
      next.edited = String(next.latitud) !== String(next.original_latitud) || String(next.longitud) !== String(next.original_longitud);
      return next;
    }));
  };

  const updateWizardBulkCoordsText = (id, value) => {
    const parts = value.split(',');
    const latitud = (parts[0] || '').trim();
    const longitud = parts.length > 1 ? parts.slice(1).join(',').trim() : '';
    updateWizardBulkRow(id, 'latitud', latitud);
    updateWizardBulkRow(id, 'longitud', longitud);
  };

  const copyWizardBulkCoords = async (row) => {
    const text = `${row.latitud}, ${row.longitud}`.trim();
    if (!row.latitud || !row.longitud) {
      setNotice('La fila no tiene coordenadas completas para copiar.');
      return;
    }
    try {
      await navigator.clipboard.writeText(text);
      setNotice(`Coordenadas copiadas de ID ${row.id}.`);
    } catch {
      setError('No se pudo copiar al portapapeles.');
    }
  };

  const guardarLoteMasivo = async () => {
    if (!wizardBulkRows.length) {
      setNotice('No hay propuestas masivas para guardar.');
      return;
    }

    const rowsToSave = wizardBulkRows
      .filter((row) => row.latitud !== '' && row.longitud !== '')
      .slice(0, 10)
      .map((row) => ({
        id: row.id,
        latitud: Number(row.latitud),
        longitud: Number(row.longitud),
      }));

    if (!rowsToSave.length) {
      setError('Debes completar al menos una fila con latitud y longitud para guardar.');
      return;
    }

    setWizardBusy(true);
    try {
      await api.geocodingWizardBulkSave(token, { rows: rowsToSave });
      setNotice(`Lote guardado (${rowsToSave.length} domicilios).`);
      setWizardBulkRows([]);
      await refreshGeocodingAudit();
    } catch (err) {
      setError(err.message);
    } finally {
      setWizardBusy(false);
    }
  };

  const seleccionarDomicilioWizard = (item) => {
    setWizardSelected(item);
    setWizardManual({
      latitud: item.latitud ?? '',
      longitud: item.longitud ?? '',
    });
    setWizardAttempts([]);
    setWizardQuery('');
  };

  const intentarGeocodificarWizard = async (domicilioId, query = '') => {
    setWizardBusy(true);
    try {
      const result = await api.geocodingWizardAttempt(token, domicilioId, {
        query: query || undefined,
        use_variants: true,
      });
      setWizardAttempts(result.attempts || []);
      const nextSelected = result.domicilio || wizardSelected;
      setWizardSelected(nextSelected);
      if (result.resolved?.lat !== undefined && result.resolved?.lng !== undefined) {
        // Precargar propuesta para que el admin valide o sobrescriba manualmente.
        setWizardManual({
          latitud: String(result.resolved.lat),
          longitud: String(result.resolved.lng),
        });
      } else {
        const matchedAttempt = (result.attempts || []).find((att) => att.matched && att.lat !== null && att.lng !== null);
        if (matchedAttempt) {
          setWizardManual({
            latitud: String(matchedAttempt.lat),
            longitud: String(matchedAttempt.lng),
          });
        } else if (nextSelected) {
          setWizardManual({
            latitud: nextSelected.latitud ?? '',
            longitud: nextSelected.longitud ?? '',
          });
        }
      }
      if (result.success) {
        setNotice('Domicilio geocodificado correctamente.');
        await refreshGeocodingAudit();
      } else {
        setNotice('Sin match automático. Puedes corregir query, cargar manual o aceptar fallback.');
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setWizardBusy(false);
    }
  };

  const guardarManualWizard = async (domicilioId) => {
    setWizardBusy(true);
    try {
      const updated = await api.geocodingWizardManual(token, domicilioId, {
        latitud: Number(wizardManual.latitud),
        longitud: Number(wizardManual.longitud),
        geocodificado: 1,
      });
      setWizardSelected(updated);
      setNotice('Coordenadas manuales guardadas.');
      await refreshGeocodingAudit();
    } catch (err) {
      setError(err.message);
    } finally {
      setWizardBusy(false);
    }
  };

  const getWizardCoordsText = () => {
    if (wizardManual.latitud === '' && wizardManual.longitud === '') {
      return '';
    }
    return `${wizardManual.latitud}, ${wizardManual.longitud}`;
  };

  const handleWizardCoordsTextChange = (value) => {
    const parts = value.split(',');
    const latitud = (parts[0] || '').trim();
    const longitud = parts.length > 1 ? parts.slice(1).join(',').trim() : '';
    setWizardManual({ latitud, longitud });
  };

  const copyWizardCoords = async () => {
    const text = getWizardCoordsText();
    if (!text) {
      setNotice('No hay coordenadas para copiar.');
      return;
    }
    try {
      await navigator.clipboard.writeText(text);
      setNotice('Coordenadas copiadas al portapapeles.');
    } catch {
      setError('No se pudo copiar al portapapeles.');
    }
  };

  const marcarFallbackWizard = async (domicilioId) => {
    setWizardBusy(true);
    try {
      const updated = await api.geocodingWizardFallback(token, domicilioId);
      setWizardSelected(updated);
      setNotice('Domicilio marcado con fallback. Puedes volver a intentar luego.');
      await refreshGeocodingAudit();
    } catch (err) {
      setError(err.message);
    } finally {
      setWizardBusy(false);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  useEffect(() => {
    const syncViewFromHash = () => {
      const hash = window.location.hash.replace('#', '');
      if (ADMIN_VIEWS.includes(hash)) {
        setCurrentView(hash);
        return;
      }
      window.location.hash = '#admin-home';
      setCurrentView('admin-home');
    };

    syncViewFromHash();
    window.addEventListener('hashchange', syncViewFromHash);
    return () => window.removeEventListener('hashchange', syncViewFromHash);
  }, []);

  const byRole = useMemo(() => {
    const counts = { admin: 0, supervisor: 0, consultor: 0 };
    usuarios.forEach((u) => {
      counts[u.rol] += 1;
    });
    return counts;
  }, [usuarios]);

  const selectedLote = useMemo(
    () => lotes.find((l) => String(l.id) === String(selectedLoteId)) || null,
    [lotes, selectedLoteId],
  );

  const handleCreateUser = async (event) => {
    event.preventDefault();
    setSavingUser(true);
    setNotice('');

    try {
      await api.createUsuario(token, newUser);
      setNewUser({ nombre: '', email: '', password: '', rol: 'consultor' });
      setNotice('Usuario creado correctamente.');
      await loadData();
    } catch (err) {
      setError(err.message);
    } finally {
      setSavingUser(false);
    }
  };

  const handleExportDomicilios = async () => {
    setError('');
    setNotice('');
    try {
      const blob = await api.exportDomiciliosXlsx(token);
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `domicilios_${dayjs().format('YYYY-MM-DD_HHmm')}.xlsx`;
      a.rel = 'noopener';
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      setNotice('Exportacion de domicilios descargada.');
    } catch (err) {
      setError(err.message);
    }
  };

  const handleImportToSelectedLote = async () => {
    if (!selectedLoteId) {
      setError('Selecciona un lote antes de importar.');
      return;
    }
    if (!importFile) {
      setError('Selecciona un archivo .xlsx para importar.');
      return;
    }

    setImporting(true);
    setError('');
    setNotice('');
    try {
      const result = await api.importDomicilios(token, importFile, 0, Number(selectedLoteId));
      setNotice(`Importacion completa (insertados: ${result.inserted}, omitidos: ${result.skipped}). Lote actualizado con ${result.linked_to_lote} domicilios vinculados.`);
      setImportFile(null);
      await Promise.all([loadData(), recargarLotesMes(), refreshGeocodingAudit(buildWizardQuery(1, true))]);
    } catch (err) {
      setError(err.message);
    } finally {
      setImporting(false);
    }
  };

  const recargarLotesMes = async () => {
    const lotesData = await api.getLotes(token, `anio=${loteDraft.anio}&mes=${loteDraft.mes}`);
    setLotes(lotesData);
    if (lotesData[0] && !selectedLoteId) {
      setSelectedLoteId(String(lotesData[0].id));
    }
  };

  const crearLote = async () => {
    setError('');
    setNotice('');
    try {
      const lote = await api.createLote(token, {
        anio: Number(loteDraft.anio),
        mes: Number(loteDraft.mes),
        numero_lote: Number(loteDraft.numero_lote),
        titulo: loteDraft.titulo.trim() || undefined,
        observacion: loteDraft.observacion.trim() || undefined,
      });
      setSelectedLoteId(String(lote.id));
      setNotice(`Lote #${lote.numero_lote} creado.`);
      await recargarLotesMes();
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
    setNotice('');
    try {
      await api.assignLoteToConsultor(token, Number(selectedLoteId), { consultor_id: Number(assignConsultorId) });
      setNotice('Lote asignado al consultor correctamente.');
    } catch (err) {
      setError(err.message);
    }
  };

  useEffect(() => {
    if (!selectedLoteId) {
      return;
    }
    refreshGeocodingAudit(buildWizardQuery(1, true)).catch((err) => setError(err.message));
  }, [selectedLoteId]);

  const openEditDialog = async (user) => {
    setError('');
    try {
      const fresh = await api.getUsuarioById(token, user.id);
      setSelectedUser(fresh);
      setEditUser({
        nombre: fresh.nombre,
        email: fresh.email,
        rol: fresh.rol,
        activo: Number(Boolean(fresh.activo)),
      });
      setEditOpen(true);
    } catch (err) {
      setError(err.message);
    }
  };

  const openSectionDialog = async (sectionId) => {
    setError('');
    try {
      const fresh = await api.getSeccionById(token, sectionId);
      setSelectedSection(fresh);
      setSectionDescription(fresh.descripcion || '');
      setSectionEditOpen(true);
    } catch (err) {
      setError(err.message);
    }
  };

  const saveSection = async () => {
    if (!selectedSection) {
      return;
    }

    setSavingSection(true);
    setError('');
    setNotice('');

    try {
      await api.updateSeccion(token, selectedSection.id, sectionDescription.trim());
      setSectionEditOpen(false);
      setNotice('Descripcion de seccion actualizada.');
      await loadData();
    } catch (err) {
      setError(err.message);
    } finally {
      setSavingSection(false);
    }
  };

  const saveUserChanges = async () => {
    if (!selectedUser) {
      return;
    }

    setSavingEdit(true);
    setError('');
    setNotice('');

    try {
      await api.updateUsuario(token, selectedUser.id, {
        nombre: editUser.nombre,
        email: editUser.email,
        rol: editUser.rol,
        activo: Number(editUser.activo),
      });
      setEditOpen(false);
      setNotice('Usuario actualizado correctamente.');
      await loadData();
    } catch (err) {
      setError(err.message);
    } finally {
      setSavingEdit(false);
    }
  };

  const openPasswordDialog = (user) => {
    setSelectedUser(user);
    setNewPassword('');
    setPasswordOpen(true);
  };

  const savePassword = async () => {
    if (!selectedUser) {
      return;
    }

    setSavingPassword(true);
    setError('');
    setNotice('');

    try {
      await api.updateUsuarioPassword(token, selectedUser.id, newPassword);
      setPasswordOpen(false);
      setNotice('Contrasena actualizada.');
    } catch (err) {
      setError(err.message);
    } finally {
      setSavingPassword(false);
    }
  };

  const deactivateUser = async (user) => {
    const ok = window.confirm(`Desactivar a ${user.nombre}?`);
    if (!ok) {
      return;
    }

    setError('');
    setNotice('');

    try {
      await api.deactivateUsuario(token, user.id);
      setNotice('Usuario desactivado.');
      await loadData();
    } catch (err) {
      setError(err.message);
    }
  };

  const createJornada = async (event) => {
    event.preventDefault();
    setError('');
    setNotice('');

    try {
      await api.createJornada(token, {
        consultor_id: Number(newJornada.consultor_id),
        fecha: newJornada.fecha,
      });
      setCreateJornadaOpen(false);
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
      await api.generarRuta(token, jornadaId);
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

  const ejecutarLlm = async (jornadaId) => {
    setError('');
    try {
      await api.priorizarJornada(token, jornadaId);
      setNotice('Sugerencia LLM solicitada.');
    } catch (err) {
      setError(err.message);
    }
  };

  const openEditJornada = (jornada) => {
    setError('');
    setEditingJornada(jornada);
    setJornadaDraft({
      consultor_id: String(jornada.consultor_id),
      fecha: jornada.fecha,
    });
    setEditJornadaOpen(true);
  };

  const confirmEditJornada = async () => {
    if (!editingJornada) {
      return;
    }

    setSavingJornadaEdit(true);
    setError('');
    setNotice('');

    try {
      await api.updateJornada(token, editingJornada.id, {
        consultor_id: Number(jornadaDraft.consultor_id),
        fecha: jornadaDraft.fecha,
      });
      setConfirmEditJornadaOpen(false);
      setEditJornadaOpen(false);
      setEditingJornada(null);
      setNotice('Jornada actualizada correctamente.');
      await loadData();
    } catch (err) {
      setError(err.message);
    } finally {
      setSavingJornadaEdit(false);
    }
  };

  const openDeleteJornadaConfirm = (jornada) => {
    setDeletingJornada(jornada);
    setConfirmDeleteJornadaOpen(true);
  };

  const openEstadoMenu = (event, jornadaId) => {
    setEstadoMenuAnchorEl(event.currentTarget);
    setEstadoMenuJornadaId(jornadaId);
  };

  const closeEstadoMenu = () => {
    setEstadoMenuAnchorEl(null);
    setEstadoMenuJornadaId(null);
  };

  const selectEstadoFromMenu = async (estado) => {
    if (!estadoMenuJornadaId) {
      return;
    }
    await cambiarEstado(estadoMenuJornadaId, estado);
    closeEstadoMenu();
  };

  const confirmDeleteJornada = async () => {
    if (!deletingJornada) {
      return;
    }

    setDeletingJornadaBusy(true);
    setError('');
    setNotice('');

    try {
      await api.deleteJornada(token, deletingJornada.id);
      setConfirmDeleteJornadaOpen(false);
      setDeletingJornada(null);
      setNotice('Jornada eliminada correctamente.');
      await loadData();
    } catch (err) {
      setError(err.message);
    } finally {
      setDeletingJornadaBusy(false);
    }
  };

  if (loading) {
    return (
      <Stack spacing={1.5} alignItems="center" py={6}>
        <CircularProgress />
        <Typography>Cargando panel de administracion...</Typography>
      </Stack>
    );
  }

  return (
    <Stack spacing={2.5}>
      <Box>
        <Typography variant="h5">Panel Admin</Typography>
        <Typography color="text.secondary">Vista modular por categoria para mejorar navegacion y foco operativo.</Typography>
      </Box>

      {error ? <Alert severity="error" onClose={() => setError('')}>{error}</Alert> : null}
      {notice ? <Alert severity="success" onClose={() => setNotice('')}>{notice}</Alert> : null}

      {currentView === 'admin-home' ? (
        <>
          <Grid2 container spacing={2}>
            <Grid2 size={{ xs: 12, md: 3 }}><StatCard title="Usuarios" value={usuarios.length} hint="Cuentas activas y de soporte" /></Grid2>
            <Grid2 size={{ xs: 12, md: 3 }}><StatCard title="Supervisores" value={byRole.supervisor} hint="Gestion de equipos" /></Grid2>
            <Grid2 size={{ xs: 12, md: 3 }}><StatCard title="Consultores" value={byRole.consultor} hint="Ejecucion en campo" /></Grid2>
            <Grid2 size={{ xs: 12, md: 3 }}><StatCard title="Domicilios" value={domicilios.total} hint="Base de visitas" /></Grid2>
          </Grid2>

          <Grid2 container spacing={2}>
            <Grid2 size={{ xs: 12, lg: 6 }}>
              <Card>
                <CardContent>
                  <Typography variant="h6" mb={1}>Resumen operativo</Typography>
                  <Typography variant="body2" color="text.secondary">
                    Home centraliza indicadores e importaciones para que el admin ejecute tareas de mantenimiento sin cambiar de modulo.
                  </Typography>
                </CardContent>
              </Card>
            </Grid2>
            <Grid2 size={{ xs: 12, lg: 6 }}>
              <Card>
                <CardContent>
                  <Typography variant="h6" mb={1.5}>Exportar domicilios</Typography>
                  <Stack spacing={1.2}>
                    <Button
                      fullWidth
                      variant="contained"
                      color="primary"
                      startIcon={<DownloadOutlinedIcon />}
                      onClick={handleExportDomicilios}
                    >
                      Exportar todas las direcciones (XLSX)
                    </Button>
                    <Typography variant="caption" color="text.secondary" display="block">
                      La importacion ahora se realiza exclusivamente desde <strong>Lotes</strong>.
                    </Typography>
                  </Stack>
                </CardContent>
              </Card>
            </Grid2>
          </Grid2>

          <Alert severity="info">
            La gestion de lotes e importacion operativa fue movida al modulo <strong>Lotes</strong> para centralizar carga, asignacion y geocodificacion.
          </Alert>
        </>
      ) : null}

      {currentView === 'admin-domicilios' ? (
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 12 }}>
            <Typography variant="h6">Base de domicilios</Typography>
            <Typography variant="body2" color="text.secondary" mb={1}>
              Importar desde Excel o exportar la tabla completa a XLSX (solo admin).
            </Typography>
          </Grid2>
          <Grid2 size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" mb={1.5}>Exportar</Typography>
                <Typography variant="body2" color="text.secondary" mb={2}>
                  Descarga todas las direcciones con geocodificacion y metadatos.
                </Typography>
                <Button
                  fullWidth
                  size="large"
                  variant="contained"
                  color="primary"
                  startIcon={<DownloadOutlinedIcon />}
                  onClick={handleExportDomicilios}
                >
                  Descargar domicilios (.xlsx)
                </Button>
              </CardContent>
            </Card>
          </Grid2>
          <Grid2 size={{ xs: 12, md: 6 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" mb={1.5}>Importar</Typography>
                <Stack spacing={1.2}>
                  <Alert severity="info">
                    La importacion de direcciones se realiza dentro del modulo <strong>Lotes</strong> y siempre queda asociada al lote seleccionado.
                  </Alert>
                </Stack>
              </CardContent>
            </Card>
          </Grid2>
        </Grid2>
      ) : null}

      {currentView === 'admin-usuarios' ? (
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 12, lg: 5 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" mb={1.5}>Crear usuario</Typography>
                <form onSubmit={handleCreateUser}>
                  <Stack spacing={1.5}>
                    <TextField label="Nombre" value={newUser.nombre} onChange={(e) => setNewUser((p) => ({ ...p, nombre: e.target.value }))} required />
                    <TextField label="Email" type="email" value={newUser.email} onChange={(e) => setNewUser((p) => ({ ...p, email: e.target.value }))} required />
                    <TextField label="Contrasena" type="password" value={newUser.password} onChange={(e) => setNewUser((p) => ({ ...p, password: e.target.value }))} required helperText="Minimo 8 caracteres" />
                    <TextField select label="Rol" value={newUser.rol} onChange={(e) => setNewUser((p) => ({ ...p, rol: e.target.value }))}>
                      {USER_ROLES.map((role) => <MenuItem key={role} value={role}>{role}</MenuItem>)}
                    </TextField>
                    <Button type="submit" variant="contained" disabled={savingUser}>{savingUser ? 'Guardando...' : 'Crear usuario'}</Button>
                  </Stack>
                </form>
              </CardContent>
            </Card>
          </Grid2>

          <Grid2 size={{ xs: 12, lg: 7 }}>
            <Card>
              <CardContent>
                <Typography variant="h6">Usuarios recientes</Typography>
                <Typography variant="body2" color="text.secondary" mb={1.5}>Gestion completa de altas y mantenimiento de cuentas.</Typography>
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>Nombre</TableCell>
                      <TableCell>Email</TableCell>
                      <TableCell>Rol</TableCell>
                      <TableCell>Activo</TableCell>
                      <TableCell>Acciones</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {usuarios.slice(0, 20).map((u) => (
                      <TableRow key={u.id} hover>
                        <TableCell>{u.nombre}</TableCell>
                        <TableCell>{u.email}</TableCell>
                        <TableCell>
                          <Chip size="small" label={u.rol} sx={{ textTransform: 'capitalize' }} />
                        </TableCell>
                        <TableCell>{u.activo ? 'Si' : 'No'}</TableCell>
                        <TableCell>
                          <Stack direction="row" spacing={0.5}>
                            <Button size="small" startIcon={<EditOutlinedIcon />} onClick={() => openEditDialog(u)}>
                              Editar
                            </Button>
                            <Button size="small" startIcon={<KeyOutlinedIcon />} onClick={() => openPasswordDialog(u)}>
                              Clave
                            </Button>
                            <Button size="small" color="warning" startIcon={<PersonOffOutlinedIcon />} onClick={() => deactivateUser(u)}>
                              Desactivar
                            </Button>
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
      ) : null}

      {currentView === 'admin-secciones' ? (
        <Card>
          <CardContent>
            <Typography variant="h6">Secciones configuradas</Typography>
            <Typography variant="body2" color="text.secondary" mb={1.2}>
              Edicion de metadata de secciones (consume GET/PUT por ID).
            </Typography>
            <TableContainer component={Paper} variant="outlined">
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>ID</TableCell>
                    <TableCell>Numero</TableCell>
                    <TableCell>Descripcion</TableCell>
                    <TableCell>Accion</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {secciones.map((sec) => (
                    <TableRow key={sec.id} hover>
                      <TableCell>{sec.id}</TableCell>
                      <TableCell>{sec.numero}</TableCell>
                      <TableCell>{sec.descripcion || '-'}</TableCell>
                      <TableCell>
                        <Button size="small" startIcon={<EditOutlinedIcon />} onClick={() => openSectionDialog(sec.id)}>
                          Editar
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

      {currentView === 'admin-lotes' ? (
        <Grid2 container spacing={2}>
          <Grid2 size={{ xs: 12, lg: 5 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" mb={1.2}>ABM de lotes mensuales</Typography>
                <Stack spacing={1.2}>
                  <TextField
                    label="Anio"
                    type="number"
                    value={loteDraft.anio}
                    onChange={(e) => setLoteDraft((p) => ({ ...p, anio: Number(e.target.value) }))}
                  />
                  <TextField
                    label="Mes"
                    type="number"
                    inputProps={{ min: 1, max: 12 }}
                    value={loteDraft.mes}
                    onChange={(e) => setLoteDraft((p) => ({ ...p, mes: Number(e.target.value) }))}
                  />
                  <TextField
                    label="Numero de lote (1..3)"
                    type="number"
                    inputProps={{ min: 1, max: 3 }}
                    value={loteDraft.numero_lote}
                    onChange={(e) => setLoteDraft((p) => ({ ...p, numero_lote: Number(e.target.value) }))}
                  />
                  <TextField
                    label="Titulo"
                    value={loteDraft.titulo}
                    onChange={(e) => setLoteDraft((p) => ({ ...p, titulo: e.target.value }))}
                  />
                  <TextField
                    label="Observacion"
                    value={loteDraft.observacion}
                    onChange={(e) => setLoteDraft((p) => ({ ...p, observacion: e.target.value }))}
                  />
                  <Stack direction="row" spacing={1}>
                    <Button variant="contained" onClick={crearLote}>Crear lote</Button>
                    <Button variant="outlined" onClick={recargarLotesMes}>Refrescar</Button>
                  </Stack>
                </Stack>
              </CardContent>
            </Card>
          </Grid2>
          <Grid2 size={{ xs: 12, lg: 7 }}>
            <Card>
              <CardContent>
                <Typography variant="h6" mb={1.2}>Asignacion e importacion por lote seleccionado</Typography>
                <Stack spacing={1.2} mb={1.5}>
                  <TextField
                    select
                    label="Lote"
                    value={selectedLoteId}
                    onChange={(e) => setSelectedLoteId(e.target.value)}
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
                  >
                    {consultores.map((c) => (
                      <MenuItem key={c.id} value={String(c.id)}>{c.nombre}</MenuItem>
                    ))}
                  </TextField>
                  <Stack direction="row" spacing={1}>
                    <Button variant="contained" onClick={asignarLoteConsultor} disabled={!selectedLoteId || !assignConsultorId}>
                      Asignar lote al consultor
                    </Button>
                  </Stack>

                  {selectedLote ? (
                    <Card variant="outlined">
                      <CardContent>
                        <Typography variant="subtitle2" mb={1}>
                          Adjuntar XLSX al lote #{selectedLote.numero_lote} - {selectedLote.titulo}
                        </Typography>
                        <Stack spacing={1}>
                          <Button
                            component="label"
                            variant="outlined"
                            startIcon={<UploadFileOutlinedIcon />}
                          >
                            Seleccionar archivo .xlsx
                            <input hidden type="file" accept=".xlsx" onChange={(e) => setImportFile(e.target.files?.[0] || null)} />
                          </Button>
                          <Typography variant="body2" color="text.secondary">
                            {importFile ? `Archivo: ${importFile.name}` : 'Sin archivo seleccionado'}
                          </Typography>
                          <Alert severity="info">
                            Formato esperado: columnas en orden calle, altura, seccion, provincia, pais, servicio.
                            Ejemplo: Col B calle, Col C altura, Col D seccion, Col E provincia, Col F pais, Col G servicio.
                          </Alert>
                          <Button
                            variant="contained"
                            color="secondary"
                            disabled={importing}
                            onClick={handleImportToSelectedLote}
                          >
                            {importing ? 'Importando...' : 'Importar y asociar al lote seleccionado'}
                          </Button>
                        </Stack>
                      </CardContent>
                    </Card>
                  ) : (
                    <Alert severity="info">Selecciona un lote en la tabla para habilitar la carga de XLSX.</Alert>
                  )}
                </Stack>

                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>ID</TableCell>
                      <TableCell>Mes</TableCell>
                      <TableCell>Lote</TableCell>
                      <TableCell>Titulo</TableCell>
                      <TableCell>Domicilios</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {lotes.map((l) => (
                      <TableRow
                        key={l.id}
                        hover
                        selected={String(l.id) === String(selectedLoteId)}
                        onClick={() => setSelectedLoteId(String(l.id))}
                        sx={{ cursor: 'pointer' }}
                      >
                        <TableCell>{l.id}</TableCell>
                        <TableCell>{l.mes}/{l.anio}</TableCell>
                        <TableCell>{l.numero_lote}</TableCell>
                        <TableCell>{l.titulo}</TableCell>
                        <TableCell>{l.total_domicilios}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </Grid2>

          <Grid2 size={{ xs: 12 }}>
            {selectedLote && Number(selectedLote.total_domicilios || 0) > 0 ? (
            <Card>
              <CardContent>
                <Stack direction={{ xs: 'column', md: 'row' }} justifyContent="space-between" spacing={1} mb={1.5}>
                  <Typography variant="h6">Wizard geocodificacion asistida (integrado a lotes)</Typography>
                  <Stack direction={{ xs: 'column', md: 'row' }} spacing={1} useFlexGap flexWrap="wrap">
                    <Button variant="outlined" color="warning" onClick={limpiarGeocodificacion} disabled={wizardBusy}>
                      Limpiar geocodificacion
                    </Button>
                    <Button variant="outlined" onClick={() => cargarWizardPage(1, true)} disabled={wizardBusy}>
                      Refrescar pendientes
                    </Button>
                    <Button variant="contained" onClick={() => geocodingMasivo('nominatim')} disabled={wizardBusy || !(wizardPage.items || []).length}>
                      Geocoding masivo
                    </Button>
                    <Button
                      variant="contained"
                      color="secondary"
                      onClick={() => geocodingMasivo('google')}
                      disabled={wizardBusy || !(wizardPage.items || []).length}
                    >
                      Geocoding masivo Google
                    </Button>
                  </Stack>
                </Stack>

                <Typography variant="body2" color="text.secondary" mb={1.2}>
                  Cobertura: {geocodingEstado.geocodificados}/{geocodingEstado.total} ({geocodingEstado.cobertura_pct}%)
                </Typography>
                <Typography variant="body2" color="text.secondary" mb={1.2}>
                  Lote masivo cargado: {wizardBulkRows.length} fila(s)
                </Typography>

                {wizardBulkRows.length ? (
                  <Box mb={1.5}>
                    <Typography variant="subtitle2" mb={1}>Lote masivo (max 10)</Typography>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>ID</TableCell>
                          <TableCell>Domicilio</TableCell>
                          <TableCell>Match</TableCell>
                          <TableCell>Origen</TableCell>
                          <TableCell>Coordenadas propuestas (latitud, longitud)</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {wizardBulkRows.map((row) => (
                          <TableRow key={`bulk-${row.id}`}>
                            <TableCell>{row.id}</TableCell>
                            <TableCell>{row.calle} {row.altura}</TableCell>
                            <TableCell>{row.matched ? 'OK' : 'Sin match'}</TableCell>
                            <TableCell>{row.edited ? 'Editada manualmente' : 'Propuesta automatica'}</TableCell>
                            <TableCell>
                              <Stack direction="row" spacing={1} alignItems="center">
                                <TextField
                                  size="small"
                                  value={`${row.latitud}, ${row.longitud}`}
                                  onChange={(e) => updateWizardBulkCoordsText(row.id, e.target.value)}
                                  sx={{ minWidth: 360 }}
                                />
                                <Tooltip title="Copiar coordenadas">
                                  <span>
                                    <IconButton color="primary" onClick={() => copyWizardBulkCoords(row)}>
                                      <ContentCopyOutlinedIcon fontSize="small" />
                                    </IconButton>
                                  </span>
                                </Tooltip>
                              </Stack>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                    <Stack direction="row" spacing={1} mt={1}>
                      <Button size="small" variant="contained" onClick={guardarLoteMasivo} disabled={wizardBusy}>
                        Guardar lote (max 10)
                      </Button>
                      <Button size="small" variant="outlined" onClick={() => setWizardBulkRows([])} disabled={wizardBusy}>
                        Limpiar lote
                      </Button>
                    </Stack>
                  </Box>
                ) : null}

                <Grid2 container spacing={2}>
                  <Grid2 size={{ xs: 12, lg: 7 }}>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>ID</TableCell>
                          <TableCell>Domicilio</TableCell>
                          <TableCell>Geocodificado</TableCell>
                          <TableCell>Accion</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {wizardPage.items.map((item) => (
                          <TableRow key={item.id} hover selected={wizardSelected?.id === item.id}>
                            <TableCell>{item.id}</TableCell>
                            <TableCell>{item.calle} {item.altura}</TableCell>
                            <TableCell>{item.geocodificado ? 'Si' : 'No'}</TableCell>
                            <TableCell>
                              <Button size="small" onClick={() => seleccionarDomicilioWizard(item)}>
                                Revisar
                              </Button>
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                    <Stack direction="row" spacing={1} mt={1}>
                      <Button size="small" disabled={wizardPage.page <= 1 || wizardBusy} onClick={() => cargarWizardPage(wizardPage.page - 1, wizardPage.only_pending)}>
                        Anterior
                      </Button>
                      <Button size="small" disabled={wizardPage.page >= wizardPage.pages || wizardBusy} onClick={() => cargarWizardPage(wizardPage.page + 1, wizardPage.only_pending)}>
                        Siguiente
                      </Button>
                      <Typography variant="caption" color="text.secondary" sx={{ alignSelf: 'center' }}>
                        Pagina {wizardPage.page}/{wizardPage.pages}
                      </Typography>
                    </Stack>
                  </Grid2>

                  <Grid2 size={{ xs: 12, lg: 5 }}>
                    {wizardSelected ? (
                      <Stack spacing={1.2}>
                        <Typography variant="subtitle1">Domicilio #{wizardSelected.id}</Typography>
                        <Typography variant="body2" color="text.secondary">
                          {wizardSelected.calle} {wizardSelected.altura} · Seccion {wizardSelected.seccion_numero}
                        </Typography>
                        <TextField
                          size="small"
                          label="Query manual (opcional)"
                          value={wizardQuery}
                          onChange={(e) => setWizardQuery(e.target.value)}
                          helperText='Ej: "Regimiento 12 de Infanteria 1234, Santa Fe"'
                        />
                        <Stack direction="row" spacing={1}>
                          <Button size="small" variant="contained" onClick={() => intentarGeocodificarWizard(wizardSelected.id, wizardQuery)} disabled={wizardBusy}>
                            Reintentar geocoding
                          </Button>
                          <Button size="small" variant="outlined" color="warning" onClick={() => marcarFallbackWizard(wizardSelected.id)} disabled={wizardBusy}>
                            Aceptar fallback
                          </Button>
                        </Stack>
                        <Stack direction="row" spacing={1} alignItems="center">
                          <TextField
                            size="small"
                            fullWidth
                            label="Coordenadas manuales (latitud, longitud)"
                            value={getWizardCoordsText()}
                            onChange={(e) => handleWizardCoordsTextChange(e.target.value)}
                            helperText='Ej: -31.597540994089602, -60.684914847417154'
                          />
                          <Tooltip title="Copiar coordenadas">
                            <span>
                              <IconButton color="primary" onClick={copyWizardCoords}>
                                <ContentCopyOutlinedIcon fontSize="small" />
                              </IconButton>
                            </span>
                          </Tooltip>
                        </Stack>
                        <Button size="small" variant="outlined" onClick={() => guardarManualWizard(wizardSelected.id)} disabled={wizardBusy}>
                          Guardar coordenadas manuales
                        </Button>

                        {wizardAttempts.length ? (
                          <Box>
                            <Typography variant="subtitle2">Intentos recientes</Typography>
                            {wizardAttempts.map((att, idx) => (
                              <Typography key={`${att.query}-${idx}`} variant="caption" display="block" color={att.matched ? 'success.main' : 'text.secondary'}>
                                {att.matched ? 'OK' : 'NO'} · {att.query}
                              </Typography>
                            ))}
                          </Box>
                        ) : null}
                      </Stack>
                    ) : (
                      <Alert severity="info">Selecciona un domicilio para iterar normalizacion y geocodificacion asistida.</Alert>
                    )}
                  </Grid2>
                </Grid2>
              </CardContent>
            </Card>
            ) : (
              <Alert severity="info">
                El wizard de geocodificacion se habilita cuando selecciones un lote y tenga domicilios asociados.
              </Alert>
            )}
          </Grid2>
        </Grid2>
      ) : null}

      {currentView === 'admin-jornadas' ? (
        <Card>
          <CardContent>
            <Stack direction="row" alignItems="center" justifyContent="space-between" mb={1.2}>
              <Typography variant="h6">Jornadas</Typography>
              <Button variant="contained" onClick={() => setCreateJornadaOpen(true)}>Nueva</Button>
            </Stack>
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
                        <Tooltip title="Ver asignaciones">
                          <span>
                            <IconButton size="small" onClick={() => verAsignaciones(j)}>
                              <VisibilityOutlinedIcon fontSize="small" />
                            </IconButton>
                          </span>
                        </Tooltip>
                        <Tooltip title={j.estado === 'borrador' ? 'Generar ruta' : 'Solo disponible en estado borrador'}>
                          <span>
                            <IconButton size="small" onClick={() => generarRuta(j.id)} disabled={j.estado !== 'borrador'}>
                              <AltRouteOutlinedIcon fontSize="small" />
                            </IconButton>
                          </span>
                        </Tooltip>
                        <Tooltip title="Priorizar con LLM">
                          <span>
                            <IconButton size="small" color="secondary" onClick={() => ejecutarLlm(j.id)}>
                              <PsychologyAltOutlinedIcon fontSize="small" />
                            </IconButton>
                          </span>
                        </Tooltip>
                        <Tooltip title={`Cambiar estado (actual: ${j.estado})`}>
                          <span>
                            <IconButton size="small" onClick={(event) => openEstadoMenu(event, j.id)}>
                              <MoreHorizOutlinedIcon fontSize="small" />
                            </IconButton>
                          </span>
                        </Tooltip>
                        <Tooltip title="Editar jornada">
                          <span>
                            <IconButton size="small" color="primary" onClick={() => openEditJornada(j)}>
                              <EditOutlinedIcon fontSize="small" />
                            </IconButton>
                          </span>
                        </Tooltip>
                        <Tooltip title="Eliminar jornada">
                          <span>
                            <IconButton size="small" color="error" onClick={() => openDeleteJornadaConfirm(j)}>
                              <DeleteOutlineOutlinedIcon fontSize="small" />
                            </IconButton>
                          </span>
                        </Tooltip>
                      </Stack>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>

            <Menu
              anchorEl={estadoMenuAnchorEl}
              open={Boolean(estadoMenuAnchorEl)}
              onClose={closeEstadoMenu}
            >
              {JORNADA_ESTADOS.map((estado) => {
                const current = jornadas.find((item) => item.id === estadoMenuJornadaId)?.estado;
                const disabled = current === estado;
                return (
                  <MenuItem key={estado} onClick={() => selectEstadoFromMenu(estado)} disabled={disabled}>
                    {estado}
                  </MenuItem>
                );
              })}
            </Menu>
          </CardContent>
        </Card>
      ) : null}

      <Dialog open={editOpen} onClose={() => setEditOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Editar usuario</DialogTitle>
        <DialogContent>
          <Stack spacing={1.5} mt={0.5}>
            <TextField label="Nombre" value={editUser.nombre} onChange={(e) => setEditUser((p) => ({ ...p, nombre: e.target.value }))} />
            <TextField label="Email" type="email" value={editUser.email} onChange={(e) => setEditUser((p) => ({ ...p, email: e.target.value }))} />
            <TextField select label="Rol" value={editUser.rol} onChange={(e) => setEditUser((p) => ({ ...p, rol: e.target.value }))}>
              {USER_ROLES.map((role) => <MenuItem key={role} value={role}>{role}</MenuItem>)}
            </TextField>
            <TextField select label="Activo" value={String(editUser.activo)} onChange={(e) => setEditUser((p) => ({ ...p, activo: Number(e.target.value) }))}>
              <MenuItem value="1">Si</MenuItem>
              <MenuItem value="0">No</MenuItem>
            </TextField>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEditOpen(false)}>Cancelar</Button>
          <Button variant="contained" onClick={saveUserChanges} disabled={savingEdit}>
            {savingEdit ? 'Guardando...' : 'Guardar cambios'}
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={passwordOpen} onClose={() => setPasswordOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Cambiar contrasena</DialogTitle>
        <DialogContent>
          <Stack spacing={1.5} mt={0.5}>
            <Typography variant="body2" color="text.secondary">
              Usuario: {selectedUser?.nombre}
            </Typography>
            <TextField
              type="password"
              label="Nueva contrasena"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              helperText="Minimo 8 caracteres"
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPasswordOpen(false)}>Cancelar</Button>
          <Button variant="contained" onClick={savePassword} disabled={savingPassword || newPassword.length < 8}>
            {savingPassword ? 'Actualizando...' : 'Actualizar clave'}
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={sectionEditOpen} onClose={() => setSectionEditOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Editar seccion #{selectedSection?.numero}</DialogTitle>
        <DialogContent>
          <Stack spacing={1.5} mt={0.5}>
            <Typography variant="body2" color="text.secondary">
              ID interno: {selectedSection?.id}
            </Typography>
            <TextField
              label="Descripcion"
              value={sectionDescription}
              onChange={(e) => setSectionDescription(e.target.value)}
              required
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setSectionEditOpen(false)}>Cancelar</Button>
          <Button variant="contained" onClick={saveSection} disabled={savingSection || !sectionDescription.trim()}>
            {savingSection ? 'Guardando...' : 'Guardar descripcion'}
          </Button>
        </DialogActions>
      </Dialog>

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

      <Dialog open={createJornadaOpen} onClose={() => setCreateJornadaOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Gestion de jornadas</DialogTitle>
        <DialogContent>
          <Box component="form" id="create-jornada-form" onSubmit={createJornada} sx={{ mt: 0.5 }}>
            <Stack spacing={1.5}>
              <TextField
                select
                label="Consultor"
                value={newJornada.consultor_id}
                onChange={(e) => setNewJornada((p) => ({ ...p, consultor_id: e.target.value }))}
                required
              >
                {consultores.map((c) => (
                  <MenuItem key={c.id} value={String(c.id)}>{c.nombre}</MenuItem>
                ))}
              </TextField>
              <TextField
                label="Fecha"
                type="date"
                value={newJornada.fecha}
                onChange={(e) => setNewJornada((p) => ({ ...p, fecha: e.target.value }))}
                InputLabelProps={{ shrink: true }}
                required
              />
            </Stack>
          </Box>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setCreateJornadaOpen(false)}>Cancelar</Button>
          <Button type="submit" form="create-jornada-form" variant="contained">Crear</Button>
        </DialogActions>
      </Dialog>

      <Dialog open={editJornadaOpen} onClose={() => setEditJornadaOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>Editar jornada #{editingJornada?.id}</DialogTitle>
        <DialogContent>
          <Stack spacing={1.5} mt={0.5}>
            <TextField
              select
              label="Consultor"
              value={jornadaDraft.consultor_id}
              onChange={(e) => setJornadaDraft((prev) => ({ ...prev, consultor_id: e.target.value }))}
              required
            >
              {consultores.map((c) => (
                <MenuItem key={c.id} value={String(c.id)}>{c.nombre}</MenuItem>
              ))}
            </TextField>
            <TextField
              label="Fecha"
              type="date"
              value={jornadaDraft.fecha}
              onChange={(e) => setJornadaDraft((prev) => ({ ...prev, fecha: e.target.value }))}
              InputLabelProps={{ shrink: true }}
              required
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setEditJornadaOpen(false)}>Cancelar</Button>
          <Button variant="contained" onClick={() => setConfirmEditJornadaOpen(true)}>
            Guardar
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={confirmEditJornadaOpen} onClose={() => setConfirmEditJornadaOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>Confirmar edicion</DialogTitle>
        <DialogContent>
          <Typography>
            Se aplicaran cambios en la jornada #{editingJornada?.id}. Queres continuar?
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmEditJornadaOpen(false)}>Cancelar</Button>
          <Button variant="contained" onClick={confirmEditJornada} disabled={savingJornadaEdit}>
            {savingJornadaEdit ? 'Guardando...' : 'Confirmar'}
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={confirmDeleteJornadaOpen} onClose={() => setConfirmDeleteJornadaOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>Confirmar eliminacion</DialogTitle>
        <DialogContent>
          <Typography>
            Vas a eliminar la jornada #{deletingJornada?.id}. Esta accion no se puede deshacer.
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmDeleteJornadaOpen(false)}>Cancelar</Button>
          <Button color="error" variant="contained" onClick={confirmDeleteJornada} disabled={deletingJornadaBusy}>
            {deletingJornadaBusy ? 'Eliminando...' : 'Eliminar'}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
