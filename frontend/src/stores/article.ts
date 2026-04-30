import { defineStore } from "pinia";
import { ref } from "vue";
import type { ArticleListParams, ArticleSummary } from "@/api/article";
import * as articleApi from "@/api/article";

export const useArticleStore = defineStore("article", () => {
  const list = ref<ArticleSummary[]>([]);
  const total = ref<number>(0);
  const page = ref<number>(1);
  const limit = ref<number>(20);
  const loading = ref<boolean>(false);

  const currentParams = ref<ArticleListParams>({});

  async function fetchList(params: ArticleListParams = {}): Promise<void> {
    loading.value = true;
    currentParams.value = { ...params };
    try {
      const r = await articleApi.fetchArticles({
        page: params.page ?? 1,
        limit: params.limit ?? 20,
        status: params.status,
        keyword: params.keyword,
      });
      list.value = r.list;
      total.value = r.total;
      page.value = r.page;
      limit.value = r.limit;
    } finally {
      loading.value = false;
    }
  }

  async function remove(id: number): Promise<void> {
    await articleApi.deleteArticle(id);
    // 删除后刷新当前页；如当前页只剩一条则回退到上一页
    const nextPage =
      list.value.length === 1 && page.value > 1 ? page.value - 1 : page.value;
    await fetchList({ ...currentParams.value, page: nextPage });
  }

  return {
    list,
    total,
    page,
    limit,
    loading,
    fetchList,
    remove,
  };
});
