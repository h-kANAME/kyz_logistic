import { Alert, Box } from '@mui/material';
import { CircleMarker, MapContainer, Popup, TileLayer } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';

const DEFAULT_CENTER = [-31.6333, -60.7];

export function RouteMap({ points, height = 320 }) {
  const geocoded = (points || []).filter((p) => p?.latitud !== null && p?.longitud !== null)
    .map((p) => ({
      ...p,
      latitud: Number(p.latitud),
      longitud: Number(p.longitud),
    }));

  const center = geocoded[0]
    ? [geocoded[0].latitud, geocoded[0].longitud]
    : DEFAULT_CENTER;

  if (!geocoded.length) {
    return <Alert severity="info">No hay coordenadas geocodificadas para mostrar en el mapa.</Alert>;
  }

  return (
    <Box sx={{ borderRadius: 2, overflow: 'hidden', border: '1px solid', borderColor: 'divider' }}>
      <MapContainer center={center} zoom={13} scrollWheelZoom style={{ height, width: '100%' }}>
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
        {geocoded.map((point) => (
          <CircleMarker
            key={point.id}
            center={[point.latitud, point.longitud]}
            radius={7}
            pathOptions={{
              color: point.estado === 'visitado' ? '#15803d' : '#0f766e',
              fillColor: point.estado === 'visitado' ? '#22c55e' : '#14b8a6',
              fillOpacity: 0.95,
            }}
          >
            <Popup>
              <strong>Parada #{point.orden ?? '-'}</strong>
              <br />
              {point.calle} {point.altura}
              <br />
              {point.servicio || 'Servicio no informado'}
            </Popup>
          </CircleMarker>
        ))}
      </MapContainer>
    </Box>
  );
}
