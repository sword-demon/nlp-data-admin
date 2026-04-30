<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import dayjs from "dayjs";
import { message } from "ant-design-vue";
import {
  fetchOrders,
  ORDER_STATUS_COLOR,
  ORDER_STATUS_LABEL,
  PAY_TYPE_LABEL,
  VIP_LEVEL_LABEL,
} from "@/api/payment";
import type { OrderItem } from "@/api/payment";

const router = useRouter();

const loading = ref<boolean>(false);
const list = ref<OrderItem[]>([]);
const pagination = reactive({
  current: 1,
  pageSize: 20,
  total: 0,
});

const columns = [
  { title: "订单号", dataIndex: "out_trade_no", width: 220, key: "out_trade_no" },
  { title: "套餐", dataIndex: "plan_type", width: 100, key: "plan_type" },
  { title: "金额", dataIndex: "amount", width: 100, key: "amount" },
  { title: "支付方式", dataIndex: "pay_type", width: 120, key: "pay_type" },
  { title: "状态", dataIndex: "status", width: 120, key: "status" },
  { title: "支付时间", dataIndex: "paid_at", width: 180, key: "paid_at" },
  { title: "创建时间", dataIndex: "created_at", width: 180, key: "created_at" },
  { title: "操作", key: "action", width: 100 },
];

async function load(page = 1): Promise<void> {
  loading.value = true;
  try {
    const r = await fetchOrders(page, pagination.pageSize);
    list.value = r.list;
    pagination.current = r.page;
    pagination.total = r.total;
  } catch (e: unknown) {
    message.error((e as Error)?.message || "加载失败");
  } finally {
    loading.value = false;
  }
}

function handleChange(p: { current?: number }): void {
  load(p.current || 1);
}

function formatDate(iso: string | null): string {
  return iso ? dayjs(iso).format("YYYY-MM-DD HH:mm") : "-";
}

function goToVip(): void {
  router.push("/vip");
}

onMounted(() => load(1));
</script>

<template>
  <div class="order-list-page">
    <a-card title="我的订单">
      <template #extra>
        <a-button type="link" @click="goToVip">返回会员中心</a-button>
      </template>

      <a-table
        :columns="columns"
        :data-source="list"
        :loading="loading"
        :pagination="pagination"
        row-key="order_id"
        size="middle"
        @change="handleChange"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'plan_type'">
            {{ VIP_LEVEL_LABEL[record.plan_type as 'monthly' | 'yearly'] }}
          </template>
          <template v-else-if="column.key === 'amount'">
            ¥{{ record.amount.toFixed(2) }}
          </template>
          <template v-else-if="column.key === 'pay_type'">
            {{ PAY_TYPE_LABEL[record.pay_type as 'wxpay' | 'alipay'] }}
          </template>
          <template v-else-if="column.key === 'status'">
            <a-tag :color="ORDER_STATUS_COLOR[record.status as keyof typeof ORDER_STATUS_COLOR]">
              {{ ORDER_STATUS_LABEL[record.status as keyof typeof ORDER_STATUS_LABEL] }}
            </a-tag>
          </template>
          <template v-else-if="column.key === 'paid_at'">
            {{ formatDate(record.paid_at) }}
          </template>
          <template v-else-if="column.key === 'created_at'">
            {{ formatDate(record.created_at) }}
          </template>
          <template v-else-if="column.key === 'action'">
            <a-button
              v-if="record.status === 'pending'"
              type="link"
              size="small"
              @click="goToVip"
            >
              继续支付
            </a-button>
            <span v-else>-</span>
          </template>
        </template>
      </a-table>
    </a-card>
  </div>
</template>

<style scoped>
.order-list-page {
  max-width: 1200px;
  margin: 0 auto;
}
</style>
