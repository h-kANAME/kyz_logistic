import { FormControl, MenuItem, Select } from '@mui/material';
import { useAppTheme } from '../context/ThemeContext';

export function ThemeSelector({ darkSurface = false, minWidth = 130 }) {
  const { themeName, setThemeName, themeOptions } = useAppTheme();

  return (
    <FormControl
      size="small"
      sx={darkSurface ? {
        minWidth,
        '& .MuiOutlinedInput-root': {
          color: 'white',
          bgcolor: 'rgba(255,255,255,0.14)',
        },
        '& .MuiSvgIcon-root': {
          color: 'white',
        },
        '& .MuiOutlinedInput-notchedOutline': {
          borderColor: 'rgba(255,255,255,0.35)',
        },
        '& .MuiOutlinedInput-root:hover .MuiOutlinedInput-notchedOutline': {
          borderColor: 'rgba(255,255,255,0.55)',
        },
      } : { minWidth }}
    >
      <Select
        value={themeName}
        onChange={(event) => setThemeName(event.target.value)}
        aria-label="Selector de tema"
      >
        {themeOptions.map((option) => (
          <MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
        ))}
      </Select>
    </FormControl>
  );
}