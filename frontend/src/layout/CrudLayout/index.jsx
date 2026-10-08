import { useEffect, useState } from 'react';

import DefaultLayout from '../DefaultLayout';

import SidePanel from '@/components/SidePanel';
import { Layout } from 'antd';
import { useCrudContext } from '@/context/crud';
import useDashboardTheme from '@/hooks/useDashboardTheme';

const { Content } = Layout;

const ContentBox = ({ children }) => {
  const { state: stateCrud } = useCrudContext();
  const { isPanelClose } = stateCrud;
  const [isDark] = useDashboardTheme();
  const [isSidePanelClose, setSidePanel] = useState(isPanelClose);

  useEffect(() => {
    let timer = [];
    if (isPanelClose) {
      timer = setTimeout(() => {
        setSidePanel(isPanelClose);
      }, 200);
    } else {
      setSidePanel(isPanelClose);
    }

    return () => clearTimeout(timer);
  }, [isPanelClose]);

  return (
    <Content
      className={`whiteBox shadow layoutPadding crud-surface ${isDark ? 'crud-surface-dark' : ''}`}
      style={{
        margin: '30px auto',
        width: '100%',
        maxWidth: '100%',
        flex: 'none',
        background: isDark ? '#1f1f1f' : '#ffffff',
        color: isDark ? 'rgba(255,255,255,0.88)' : 'rgba(0,0,0,0.88)',
        borderColor: isDark ? '#434343' : undefined,
      }}
    >
      {children}
    </Content>
  );
};

export default function CrudLayout({
  children,
  config,
  sidePanelTopContent,
  sidePanelBottomContent,
  fixHeaderPanel,
}) {
  return (
    <>
      <DefaultLayout>
        <SidePanel
          config={config}
          topContent={sidePanelTopContent}
          bottomContent={sidePanelBottomContent}
          fixHeaderPanel={fixHeaderPanel}
        ></SidePanel>

        <ContentBox> {children}</ContentBox>
      </DefaultLayout>
    </>
  );
}
