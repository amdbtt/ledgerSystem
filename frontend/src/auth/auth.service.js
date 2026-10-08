const shellUser = {
  name: 'Admin',
  surname: 'User',
  email: 'admin@localhost',
  photo: null,
  token: null,
};

export const login = async () => ({
  success: true,
  result: shellUser,
});

export const register = async () => ({
  success: true,
  result: shellUser,
});

export const verify = async () => ({
  success: true,
  result: shellUser,
});

export const resetPassword = async () => ({
  success: true,
  result: shellUser,
});

export const logout = async () => ({
  success: true,
  result: {},
});
