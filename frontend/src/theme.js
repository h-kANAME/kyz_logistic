import { createTheme } from '@mui/material/styles';

export const THEME_OPTIONS = [
  { value: 'estandar', label: 'Estandar' },
  { value: 'boquita', label: 'Boquita' },
];

export const DEFAULT_THEME_NAME = 'estandar';

const baseShape = {
  radius: 14,
  radiusLg: 18,
  pill: 999,
};

const themeDefinitions = {
  estandar: {
    name: 'Terracota Operativa',
    mode: 'light',
    brand: {
      primary: '#0f766e',
      primaryDark: '#115e59',
      primaryLight: '#14b8a6',
      accent: '#ea580c',
      accentDark: '#c2410c',
      accentLight: '#fb923c',
      support: '#0e7490',
    },
    surfaces: {
      app: '#f6f7f2',
      appGradientTop: '#f8faf8',
      appGradientBottom: '#f4f6f2',
      paper: '#ffffff',
      overlaySoft: 'rgba(255,255,255,0.18)',
      overlayStrong: 'rgba(255,255,255,0.24)',
    },
    borders: {
      soft: 'rgba(15, 118, 110, 0.08)',
    },
    effects: {
      cardShadow: '0 10px 30px rgba(15, 23, 42, 0.06)',
      focusRing: '0 0 0 3px rgba(20, 184, 166, 0.18)',
      heroGradient: 'linear-gradient(95deg, #0f766e 0%, #0e7490 55%, #ea580c 100%)',
      appGlowLeft: 'rgba(20, 184, 166, 0.18)',
      appGlowRight: 'rgba(251, 146, 60, 0.2)',
    },
    states: {
      success: '#15803d',
      warning: '#c2410c',
    },
    shape: baseShape,
  },
  boquita: {
    name: 'Boquita Dark',
    mode: 'dark',
    brand: {
      primary: '#003DA5',
      primaryDark: '#002b78',
      primaryLight: '#1E5FD4',
      accent: '#F7B500',
      accentDark: '#CC9400',
      accentLight: '#FFD34D',
      support: '#0D47A1',
    },
    surfaces: {
      app: '#060B1C',
      appGradientTop: '#081234',
      appGradientBottom: '#050913',
      paper: '#0B1738',
      overlaySoft: 'rgba(255,255,255,0.14)',
      overlayStrong: 'rgba(255,255,255,0.2)',
    },
    borders: {
      soft: 'rgba(247, 181, 0, 0.22)',
    },
    effects: {
      cardShadow: '0 16px 32px rgba(0, 0, 0, 0.45)',
      focusRing: '0 0 0 3px rgba(247, 181, 0, 0.28)',
      heroGradient: 'linear-gradient(95deg, #002b78 0%, #003DA5 58%, #F7B500 100%)',
      appGlowLeft: 'rgba(0, 61, 165, 0.26)',
      appGlowRight: 'rgba(247, 181, 0, 0.18)',
    },
    states: {
      success: '#21C177',
      warning: '#F7B500',
    },
    shape: baseShape,
  },
};

function buildTokens(themeName) {
  const selected = themeDefinitions[themeName] || themeDefinitions[DEFAULT_THEME_NAME];
  return {
    ...selected,
    currentThemeName: themeName,
  };
}

export function buildAppTheme(themeName = DEFAULT_THEME_NAME) {
  const tokens = buildTokens(themeName);
  const isBoquita = tokens.currentThemeName === 'boquita';

  return createTheme({
    palette: {
      mode: tokens.mode,
      // Boquita: el azul de marca (#003DA5) como "primary" de MUI dejaba botones/enlaces casi ilegibles sobre paper oscuro.
      // Se usa blanco para texto/iconos primary; el azul sigue en tokens.brand y en gradientes (AppBar, etc.).
      primary: isBoquita
        ? {
            main: '#FFFFFF',
            light: '#FFFFFF',
            dark: '#E2E8F0',
            contrastText: tokens.surfaces.app,
          }
        : {
            main: tokens.brand.primary,
            dark: tokens.brand.primaryDark,
            light: tokens.brand.primaryLight,
          },
      secondary: {
        main: tokens.brand.accent,
        dark: tokens.brand.accentDark,
        light: tokens.brand.accentLight,
      },
      background: {
        default: tokens.surfaces.app,
        paper: tokens.surfaces.paper,
      },
      success: {
        main: tokens.states.success,
      },
      warning: {
        main: tokens.states.warning,
      },
    },
    typography: {
      fontFamily: 'Manrope, sans-serif',
      h3: { fontWeight: 700 },
      h4: { fontWeight: 700 },
      h5: { fontWeight: 700 },
      h6: { fontWeight: 700 },
      button: { fontWeight: 700, textTransform: 'none' },
    },
    shape: {
      borderRadius: tokens.shape.radius,
    },
    custom: tokens,
    components: {
      MuiCssBaseline: {
        styleOverrides: {
          ':root': {
            colorScheme: tokens.mode,
          },
          body: {
            margin: 0,
            minHeight: '100vh',
            background: `radial-gradient(1200px 400px at 0% -10%, ${tokens.effects.appGlowLeft}, transparent 50%),
              radial-gradient(900px 350px at 100% 0%, ${tokens.effects.appGlowRight}, transparent 45%),
              linear-gradient(180deg, ${tokens.surfaces.appGradientTop} 0%, ${tokens.surfaces.appGradientBottom} 100%)`,
          },
        },
      },
      MuiAppBar: {
        styleOverrides: {
          root: {
            background: tokens.effects.heroGradient,
          },
        },
      },
      MuiCard: {
        styleOverrides: {
          root: {
            border: `1px solid ${tokens.borders.soft}`,
            boxShadow: tokens.effects.cardShadow,
            borderRadius: tokens.shape.radiusLg,
          },
        },
      },
      MuiButton: {
        defaultProps: {
          disableElevation: true,
        },
        styleOverrides: {
          root: {
            borderRadius: tokens.shape.pill,
            paddingInline: 18,
          },
          containedPrimary: {
            boxShadow: 'none',
          },
          containedSecondary: {
            boxShadow: 'none',
          },
        },
      },
      MuiChip: {
        styleOverrides: {
          root: {
            borderRadius: tokens.shape.pill,
            fontWeight: 700,
          },
        },
      },
      MuiTextField: {
        defaultProps: {
          fullWidth: true,
          variant: 'outlined',
        },
      },
      MuiOutlinedInput: {
        styleOverrides: {
          root: {
            borderRadius: tokens.shape.radius,
            '&.Mui-focused': {
              boxShadow: tokens.effects.focusRing,
            },
          },
        },
      },
    },
  });
}
