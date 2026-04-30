import { defineStore } from "pinia";
import { ref, computed } from "vue";
import * as api from "@/api/workshop";
import type {
  GeneratedImage,
  ImageAnalysis,
  Outline,
  TitleCandidate,
  WorkshopStatus,
} from "@/api/workshop";

/**
 * 创作工坊 Pinia store：承载完整的 9 态状态机 + 过程数据。
 *
 * 状态流：
 *   idle → title_selecting → outline_editing → content_generating
 *        → image_analyzing → image_generating → completed
 * （任意 → failed）
 */
export const useWorkshopStore = defineStore("workshop", () => {
  const articleId = ref<number | null>(null);
  const status = ref<WorkshopStatus | "idle">("idle");
  const topic = ref("");
  const style = ref("通用");

  const titles = ref<TitleCandidate[]>([]);
  const selectedTitleIndex = ref<number>(-1);
  const selectedTitle = ref<string>("");
  const supplement = ref<string>("");

  const outline = ref<Outline | null>(null);
  const contentBuffer = ref<string>("");
  const wordCount = ref<number>(0);
  const placeholderCount = ref<number>(0);
  const imageAnalyses = ref<ImageAnalysis[]>([]);
  const images = ref<GeneratedImage[]>([]);

  const errorMessage = ref<string>("");
  const loading = ref(false);
  let eventSource: EventSource | null = null;

  const isGenerating = computed(() =>
    ["content_generating", "image_analyzing", "image_generating"].includes(
      status.value,
    ),
  );
  const isCompleted = computed(() => status.value === "completed");
  const isFailed = computed(() => status.value === "failed");

  // ============ actions ============

  function reset(): void {
    closeStream();
    articleId.value = null;
    status.value = "idle";
    topic.value = "";
    style.value = "通用";
    titles.value = [];
    selectedTitleIndex.value = -1;
    selectedTitle.value = "";
    supplement.value = "";
    outline.value = null;
    contentBuffer.value = "";
    wordCount.value = 0;
    placeholderCount.value = 0;
    imageAnalyses.value = [];
    images.value = [];
    errorMessage.value = "";
    loading.value = false;
  }

  function closeStream(): void {
    if (eventSource) {
      eventSource.close();
      eventSource = null;
    }
  }

  async function createArticle(t: string, s: string): Promise<void> {
    loading.value = true;
    errorMessage.value = "";
    try {
      const r = await api.createArticle(t, s);
      articleId.value = r.article_id;
      status.value = r.status;
      topic.value = t;
      style.value = s;
      titles.value = r.titles;
      selectedTitleIndex.value = -1;
    } catch (e) {
      errorMessage.value = extractError(e);
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function confirmTitle(): Promise<void> {
    if (articleId.value === null) throw new Error("article not created");
    if (selectedTitleIndex.value < 0) throw new Error("please select a title");
    loading.value = true;
    errorMessage.value = "";
    try {
      const r = await api.selectTitle(
        articleId.value,
        selectedTitleIndex.value,
        supplement.value,
      );
      status.value = r.status;
      selectedTitle.value = r.selected_title;
      outline.value = r.outline;
    } catch (e) {
      errorMessage.value = extractError(e);
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function saveOutline(newOutline: Outline): Promise<void> {
    if (articleId.value === null) throw new Error("article not created");
    loading.value = true;
    errorMessage.value = "";
    try {
      await api.updateOutline(articleId.value, newOutline);
      outline.value = newOutline;
    } catch (e) {
      errorMessage.value = extractError(e);
      throw e;
    } finally {
      loading.value = false;
    }
  }

  function startGeneration(): void {
    if (articleId.value === null) throw new Error("article not created");
    closeStream();
    contentBuffer.value = "";
    imageAnalyses.value = [];
    images.value = [];
    errorMessage.value = "";

    eventSource = api.startGenerationStream(articleId.value, {
      onState: (s) => {
        status.value = s;
      },
      onContentChunk: (chunk) => {
        contentBuffer.value += chunk;
      },
      onContentCompleted: (payload) => {
        wordCount.value = payload.word_count;
        placeholderCount.value = payload.placeholder_count;
      },
      onImageAnalyzed: (payload) => {
        imageAnalyses.value = payload.analyses;
      },
      onImageReady: (img) => {
        // 逐张推送：按 placeholder_id 去重后追加
        const idx = images.value.findIndex(
          (i) => i.placeholder_id === img.placeholder_id,
        );
        if (idx >= 0) images.value[idx] = img;
        else images.value.push(img);
      },
      onImageGenerated: (payload) => {
        images.value = payload.images;
      },
      onContentUpdated: (payload) => {
        // placeholder 替换后的最终正文
        contentBuffer.value = payload.content;
        wordCount.value = payload.word_count;
      },
      onError: (payload) => {
        errorMessage.value = payload.agent
          ? `[${payload.agent}] ${payload.message}`
          : payload.message;
        status.value = "failed";
      },
      onDone: () => {
        closeStream();
      },
      onTransportError: () => {
        if (!isCompleted.value) {
          errorMessage.value = errorMessage.value || "SSE connection lost";
        }
      },
    });
  }

  async function refreshStatus(): Promise<void> {
    if (articleId.value === null) return;
    const payload = await api.fetchStatus(articleId.value);
    status.value = payload.status;
    if (payload.generated_titles) titles.value = payload.generated_titles;
    if (payload.selected_title) selectedTitle.value = payload.selected_title;
    if (payload.outline) outline.value = payload.outline;
    wordCount.value = payload.word_count;
  }

  return {
    articleId,
    status,
    topic,
    style,
    titles,
    selectedTitleIndex,
    selectedTitle,
    supplement,
    outline,
    contentBuffer,
    wordCount,
    placeholderCount,
    imageAnalyses,
    images,
    errorMessage,
    loading,
    isGenerating,
    isCompleted,
    isFailed,
    reset,
    closeStream,
    createArticle,
    confirmTitle,
    saveOutline,
    startGeneration,
    refreshStatus,
  };
});

function extractError(e: unknown): string {
  if (typeof e === "object" && e !== null) {
    const anyE = e as {
      response?: { data?: { message?: string } };
      message?: string;
    };
    return anyE.response?.data?.message ?? anyE.message ?? "unknown error";
  }
  return String(e);
}
