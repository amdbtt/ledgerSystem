import { useEffect, useState } from 'react';

export const DASHBOARD_THEME_KEY = 'idurar_dashboard_theme';
export const DASHBOARD_THEME_EVENT = 'idurar-dashboard-theme-change';

function readIsDark() {
  try {
    return localStorage.getItem(DASHBOARD_THEME_KEY) === 'dark';
  } catch {
    return false;
  }
}

function applyDocumentTheme(isDark) {
  const root = document.documentElement;
  const body = document.body;

  root.classList.toggle('app-theme-dark', isDark);
  body.classList.toggle('app-theme-dark', isDark);
  root.style.colorScheme = isDark ? 'dark' : 'light';
}

export default function useDashboardTheme() {
  const [isDark, setIsDark] = useState(readIsDark);

  useEffect(() => {
    applyDocumentTheme(isDark);
  }, [isDark]);

  useEffect(() => {
    const onThemeChange = (event) => {
      setIsDark(event.detail === 'dark');
    };

    const onStorage = (event) => {
      if (event.key === DASHBOARD_THEME_KEY) {
        setIsDark(event.newValue === 'dark');
      }
    };

    window.addEventListener(DASHBOARD_THEME_EVENT, onThemeChange);
    window.addEventListener('storage', onStorage);

    return () => {
      window.removeEventListener(DASHBOARD_THEME_EVENT, onThemeChange);
      window.removeEventListener('storage', onStorage);
    };
  }, []);

  const setTheme = (dark) => {
    const next = dark ? 'dark' : 'light';
    setIsDark(dark);
    applyDocumentTheme(dark);
    try {
      localStorage.setItem(DASHBOARD_THEME_KEY, next);
    } catch {
      // ignore storage errors
    }
    window.dispatchEvent(new CustomEvent(DASHBOARD_THEME_EVENT, { detail: next }));
  };

  return [isDark, setTheme];
}
