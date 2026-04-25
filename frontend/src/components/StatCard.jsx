import TrendingUpIcon from '@mui/icons-material/TrendingUp';
import { Card, CardContent, Stack, Typography } from '@mui/material';

export function StatCard({ title, value, hint }) {
  return (
    <Card className="stagger-item">
      <CardContent>
        <Stack direction="row" justifyContent="space-between" alignItems="center" mb={1}>
          <Typography variant="overline" color="text.secondary">
            {title}
          </Typography>
          <TrendingUpIcon color="primary" fontSize="small" />
        </Stack>
        <Typography variant="h4" lineHeight={1.1} mb={0.5}>
          {value}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {hint}
        </Typography>
      </CardContent>
    </Card>
  );
}
