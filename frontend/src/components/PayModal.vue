<script setup lang="ts">
import { ref, watch, computed, onBeforeUnmount } from "vue";
import { message } from "ant-design-vue";
import {
  createOrder,
  queryOrderStatus,
  VIP_LEVEL_LABEL,
  PAY_TYPE_LABEL,
} from "@/api/payment";
import type { PayType, PlanType, CreateOrderResult } from "@/api/payment";

const props = defineProps<{
  open: boolean;
  planType: PlanType | null;
  amount: number;
}>();

const emit = defineEmits<{
  (e: "update:open", v: boolean): void;
  (e: "paid"): void;
}>();

const payType = ref<PayType>("wxpay");
const creating = ref<boolean>(false);
const order = ref<CreateOrderResult | null>(null);
const status = ref<"idle" | "pending" | "paid" | "failed">("idle");
let pollTimer: number | null = null;

const qrcodeSrc = computed<string>(() => {
  if (!order.value) return "";
  const qr = order.value.qrcode || order.value.pay_url;
  if (!qr) return "";
  // 后端返回的是支付链接（或二维码内容）。用在线服务渲染为图片。
  return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(qr)}`;
});

const planLabel = computed<string>(() =>
  props.planType ? VIP_LEVEL_LABEL[props.planType] : "",
);

function close(): void {
  stopPolling();
  order.value = null;
  status.value = "idle";
  emit("update:open", false);
}

async function handleCreate(): Promise<void> {
  if (!props.planType) {
    message.error("套餐类型缺失");
    return;
  }
  creating.value = true;
  try {
    order.value = await createOrder(props.planType, payType.value);
    status.value = "pending";
    startPolling(order.value.out_trade_no);
  } catch (e: unknown) {
    const msg =
      (e as { response?: { data?: { message?: string } } })?.response?.data
        ?.message || (e as Error)?.message;
    message.error(`创建订单失败：${msg}`);
  } finally {
    creating.value = false;
  }
}

function startPolling(outTradeNo: string): void {
  stopPolling();
  pollTimer = window.setInterval(async () => {
    try {
      const r = await queryOrderStatus(outTradeNo);
      if (r.status === "paid") {
        status.value = "paid";
        stopPolling();
        message.success("支付成功，VIP 已激活！");
        emit("paid");
        setTimeout(() => close(), 1500);
      } else if (r.status === "failed") {
        status.value = "failed";
        stopPolling();
      }
    } catch {
      // 静默忽略，继续轮询
    }
  }, 3000);
}

function stopPolling(): void {
  if (pollTimer !== null) {
    window.clearInterval(pollTimer);
    pollTimer = null;
  }
}

// 关闭弹窗时清理状态
watch(
  () => props.open,
  (v) => {
    if (!v) {
      stopPolling();
      order.value = null;
      status.value = "idle";
    }
  },
);

onBeforeUnmount(() => stopPolling());
</script>

<template>
  <a-modal
    :open="open"
    title="开通 VIP 会员"
    :footer="null"
    :mask-closable="false"
    width="460px"
    @update:open="(v: boolean) => emit('update:open', v)"
  >
    <div class="pay-modal">
      <div class="summary">
        <div class="plan-name">{{ planLabel }}</div>
        <div class="price">¥{{ amount.toFixed(2) }}</div>
      </div>

      <div v-if="status === 'idle'" class="pay-type-select">
        <div class="label">支付方式</div>
        <a-radio-group v-model:value="payType" button-style="solid">
          <a-radio-button value="wxpay">{{
            PAY_TYPE_LABEL.wxpay
          }}</a-radio-button>
          <a-radio-button value="alipay">{{
            PAY_TYPE_LABEL.alipay
          }}</a-radio-button>
        </a-radio-group>

        <a-button
          type="primary"
          size="large"
          block
          style="margin-top: 16px"
          :loading="creating"
          @click="handleCreate"
        >
          生成支付二维码
        </a-button>
      </div>

      <div v-else-if="status === 'pending' && order" class="qrcode-wrapper">
        <div class="qrcode-hint">
          请使用{{ PAY_TYPE_LABEL[payType] }}扫码支付
        </div>
        <img :src="qrcodeSrc" alt="支付二维码" class="qrcode-img" />
        <div class="trade-no">订单号：{{ order.out_trade_no }}</div>
        <div class="polling">
          <a-spin size="small" /> 等待支付完成...
        </div>
        <a-button block style="margin-top: 12px" @click="close">
          取消支付
        </a-button>
      </div>

      <div v-else-if="status === 'paid'" class="paid-tip">
        <a-typography-title :level="4" type="success">
          ✓ 支付成功
        </a-typography-title>
      </div>
    </div>
  </a-modal>
</template>

<style scoped>
.pay-modal {
  padding: 8px 0;
}
.summary {
  text-align: center;
  padding: 16px;
  background: #fafafa;
  border-radius: 8px;
  margin-bottom: 24px;
}
.plan-name {
  font-size: 16px;
  color: rgba(0, 0, 0, 0.65);
}
.price {
  font-size: 28px;
  font-weight: 600;
  color: #1677ff;
  margin-top: 4px;
}
.pay-type-select .label {
  margin-bottom: 8px;
  color: rgba(0, 0, 0, 0.65);
}
.qrcode-wrapper {
  text-align: center;
}
.qrcode-hint {
  font-size: 14px;
  color: rgba(0, 0, 0, 0.65);
  margin-bottom: 12px;
}
.qrcode-img {
  width: 220px;
  height: 220px;
  border: 1px solid #f0f0f0;
  padding: 8px;
  border-radius: 4px;
}
.trade-no {
  font-size: 12px;
  color: rgba(0, 0, 0, 0.45);
  margin-top: 12px;
  font-family: monospace;
}
.polling {
  margin-top: 12px;
  color: rgba(0, 0, 0, 0.65);
}
.paid-tip {
  text-align: center;
  padding: 32px 0;
}
</style>
