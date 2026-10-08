import { useLayoutEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';

import { Layout, ConfigProvider, theme } from 'antd';

import Navigation from '@/apps/Navigation/NavigationContainer';

import HeaderContent from '@/apps/Header/HeaderContainer';
import PageLoader from '@/components/PageLoader';

import { settingsAction } from '@/redux/settings/actions';

import { selectSettings } from '@/redux/settings/selectors';

import AppRouter from '@/router/AppRouter';

import useResponsive from '@/hooks/useResponsive';
import useDashboardTheme from '@/hooks/useDashboardTheme';

export default function ErpCrmApp() {
  const { Content } = Layout;
  const { isMobile } = useResponsive();
  const [isDark] = useDashboardTheme();
  const dispatch = useDispatch();

  useLayoutEffect(() => {
    dispatch(settingsAction.list({ entity: 'setting' }));
  }, []);

  const { isSuccess: settingIsloaded } = useSelector(selectSettings);

  const shellBackground = isDark ? '#141414' : '#ffffff';
  const contentStyle = isMobile
    ? {
        margin: '40px auto 30px',
        overflow: 'initial',
        width: '100%',
        padding: '0 25px',
        maxWidth: 'none',
        background: shellBackground,
        minHeight: 'calc(100vh - 64px)',
      }
    : {
        margin: '40px auto 30px',
        overflow: 'initial',
        width: '100%',
        padding: '0 50px',
        maxWidth: 1400,
        background: shellBackground,
        minHeight: 'calc(100vh - 64px)',
      };

  if (settingIsloaded)
    return (
      <ConfigProvider
        theme={{
          algorithm: isDark ? theme.darkAlgorithm : theme.defaultAlgorithm,
          token: {
            colorPrimary: '#339393',
            colorLink: isDark ? '#69b1ff' : '#1640D6',
            borderRadius: 0,
            colorBgBase: isDark ? '#141414' : '#ffffff',
            colorBgContainer: isDark ? '#1f1f1f' : '#ffffff',
            colorBgLayout: isDark ? '#141414' : '#ffffff',
          },
        }}
      >
        <Layout
          hasSider
          className={isDark ? 'app-shell app-shell-dark' : 'app-shell'}
          style={{
            minHeight: '100vh',
            background: shellBackground,
          }}
        >
          <Navigation />

          <Layout
            style={{
              marginLeft: isMobile ? 0 : undefined,
              background: shellBackground,
              minHeight: '100vh',
            }}
          >
            <HeaderContent />
            <Content style={contentStyle}>
              <AppRouter />
            </Content>
          </Layout>
        </Layout>
      </ConfigProvider>
    );
  else return <PageLoader />;
}
