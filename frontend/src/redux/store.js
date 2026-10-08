import { configureStore } from '@reduxjs/toolkit';

import rootReducer from './rootReducer';
import storePersist from './storePersist';

const AUTH_INITIAL_STATE = {
  current: {
    name: 'Admin',
    surname: 'User',
    email: 'admin@localhost',
    photo: null,
    token: null,
  },
  isLoggedIn: true,
  isLoading: false,
  isSuccess: true,
};

// Drop any previous API session / cached settings so the UI shell uses PKR defaults.
storePersist.remove('auth');
storePersist.remove('isLogout');
storePersist.remove('settings');

const initialState = { auth: AUTH_INITIAL_STATE };

const store = configureStore({
  reducer: rootReducer,
  preloadedState: initialState,
  devTools: import.meta.env.PROD === false,
});

console.log(
  '🚀 Welcome to IDURAR ERP CRM! Did you know that we also offer commercial customization services? Contact us at hello@idurarapp.com for more information.'
);

export default store;
