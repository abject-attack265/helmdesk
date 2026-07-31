/**
 * 文件说明：知识库资源管理器侧边栏在「非知识库页」（如经验提炼各页）的导航处理。
 * 树节点点击/新建/编辑统一跳转到知识库列表页并携带对应的选中与面板参数
 * （kb / group / panel=kb-create|kb-edit），与列表页内的虚拟子页状态一一对应。
 */
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import type {
  KnowledgeBaseCategory,
  KnowledgeBaseData,
} from '@/types/generated';
import { router } from '@inertiajs/vue3';

type ExplorerNavigationHandlers = {
  openKb: (kbId: string) => void;
  openGroup: (kbId: string, groupId: string | null) => void;
  openCreateKb: (category: KnowledgeBaseCategory) => void;
  openEditKb: (kb: KnowledgeBaseData) => void;
};

export function useKnowledgeBaseExplorerNavigation(): ExplorerNavigationHandlers {
  const visitList = (query: Record<string, string>): void => {
    router.visit(
      KnowledgeBase.ListKnowledgeBasesAction.url({
        query,
      }),
    );
  };

  return {
    openKb: (kbId) => visitList({ kb: kbId }),
    openGroup: (kbId, groupId) =>
      visitList({ kb: kbId, ...(groupId ? { group: groupId } : {}) }),
    openCreateKb: (category) => visitList({ panel: 'kb-create', category }),
    openEditKb: (kb) => visitList({ kb: kb.id, panel: 'kb-edit' }),
  };
}
