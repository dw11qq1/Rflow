<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, toRaw, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { Columns3, Plus, MoreVertical, Pencil, Trash2, BarChart3, ArrowLeft, GripVertical, Calendar, User as UserIcon, UserPlus, Crown, ShieldCheck, Mail, UserMinus, Users, AlertTriangle, MessageSquare, Flag, CheckSquare, History } from '@lucide/vue';
import { index as boardsIndex, show as boardsShow, update as boardsUpdate, destroy as boardsDestroy, retro as boardsRetro } from '@/routes/boards';
import { store as cardStore, update as cardUpdate, destroy as cardDestroy, move as cardMove } from '@/routes/cards';
import { store as columnStore, update as columnUpdate, destroy as columnDestroy } from '@/routes/columns';
import { store as commentStore, destroy as commentDestroy } from '@/routes/comments';
import { useBoardRealtime } from '@/composables/useBoardRealtime';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    DialogClose,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogFooter,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogAction,
    AlertDialogCancel,
} from '@/components/ui/alert-dialog';
import type { Auth } from '@/types';

interface Member {
    id: number;
    name: string;
    pivot?: { role: string };
}
interface Comment {
    id: number;
    body: string;
    user: { id: number; name: string } | null;
    created_at: string;
    pending?: boolean;
}
interface Card {
    id: number;
    title: string;
    description: string | null;
    position: number;
    column_id: number;
    assignee_id: number | null;
    created_by: number;
    due_date: string | null;
    priority: 'low' | 'medium' | 'high';
    color: string | null;
    assignee: { id: number; name: string } | null;
    labels: { id: number; name: string; color: string }[];
    subtasks: { id: number; title: string; is_complete: boolean; position: number }[];
    comments: Comment[];
}
interface Column {
    id: number;
    name: string;
    position: number;
    wip_limit: number | null;
    cards: Card[];
}
interface Board {
    id: number;
    name: string;
    slug: string;
    owner_id: number;
    is_archived: boolean;
    columns: Column[];
    members: Member[];
    labels: { id: number; name: string; color: string }[];
}

const props = defineProps<{ board: Board }>();

const { t } = useI18n();

const page = usePage();
const auth = page.props.auth as Auth;
const isOwner = () => props.board.owner_id === auth.user.id;

// 实时协同：订阅 presence 频道，获取在线成员（他人改动能即时刷新看板）
const { onlineMembers, connected, editing, whisper } = useBoardRealtime(() => props.board.slug);

// 当前用户在该看板的角色与权限
const myRole = computed(() => {
    if (isOwner()) return 'owner';
    const me = board.value.members.find((m) => m.id === auth.user.id);
    return me?.pivot?.role ?? 'member';
});
const canManageMembers = computed(() => myRole.value !== 'member');

function roleLabel(role: string): string {
    return t(`members.role.${role}`);
}
function memberRole(m: Member): string {
    if (m.id === props.board.owner_id) return 'owner';
    return m.pivot?.role ?? 'member';
}

// 本地可写副本（用于乐观更新），服务端返回新数据后同步
const board = ref<Board>(structuredClone(toRaw(props.board)));
watch(
    () => props.board,
    (b) => {
        if (!b || !b.columns) return;
        board.value = structuredClone(toRaw(b));
        // 实时刷新后，若卡片详情对话框仍打开，用服务端最新数据同步，避免对话框内容过期
        if (dialogOpen.value && selectedCard.value) {
            const fresh = findCard(selectedCard.value.id);
            if (fresh) selectedCard.value = fresh;
        }
    },
    { deep: true },
);

// 看板加载 / 操作反馈：Inertia 发起访问（切换看板、增删卡片/列、拖拽等）时显示 loading，完成即隐藏
const isLoading = ref(false);
let offStart: (() => void) | null = null;
let offFinish: (() => void) | null = null;
onMounted(() => {
    offStart = router.on('start', () => (isLoading.value = true));
    offFinish = router.on('finish', () => (isLoading.value = false));
});
onBeforeUnmount(() => {
    offStart?.();
    offFinish?.();
});

// 空看板：没有任何列时给出引导
const isEmptyBoard = () => board.value.columns.length === 0;

function findCard(cardId: number): Card | undefined {
    for (const col of board.value.columns) {
        const c = col.cards.find((x) => x.id === cardId);
        if (c) return c;
    }
    return undefined;
}

/* ---------------- 拖拽 ---------------- */
const dragCardId = ref<number | null>(null);
const dragFromCol = ref<number | null>(null);
const overColId = ref<number | null>(null);
const overIndex = ref<number | null>(null);

function onCardDragStart(e: DragEvent, card: Card, colId: number) {
    dragCardId.value = card.id;
    dragFromCol.value = colId;
    e.dataTransfer?.setData('text/plain', String(card.id));
    if (e.dataTransfer) e.dataTransfer.effectAllowed = 'move';
}
function onCardDragOver(e: DragEvent, colId: number, index: number) {
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
    overColId.value = colId;
    overIndex.value = index;
}
function onColumnDragOver(e: DragEvent) {
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
}
function onDragEnd() {
    dragCardId.value = null;
    dragFromCol.value = null;
    overColId.value = null;
    overIndex.value = null;
}
// 移动请求串行队列：保证同一时刻只有一个移动请求在飞，
// 彻底消除"上一帧移动请求被下一帧拖拽取消"导致的服务端漏记与卡片弹回
let moveChain: Promise<void> = Promise.resolve();

function onColumnDrop(colId: number) {
    if (dragCardId.value === null || dragFromCol.value === null) return;

    const fromCol = board.value.columns.find((c) => c.id === dragFromCol.value);
    const toCol = board.value.columns.find((c) => c.id === colId);
    if (!fromCol || !toCol) return;

    const idx = fromCol.cards.findIndex((c) => c.id === dragCardId.value);
    if (idx === -1) return;

    const [card] = fromCol.cards.splice(idx, 1);
    card.column_id = colId;

    const insertAt =
        overColId.value === colId && overIndex.value !== null ? overIndex.value : toCol.cards.length;
    toCol.cards.splice(insertAt, 0, card);

    dragCardId.value = null;
    dragFromCol.value = null;
    overColId.value = null;
    overIndex.value = null;

    // 始终发送完整看板顺序：即使有中间态未达，最终态也必定正确
    // （controller 按 items 幂等重写每张卡的 column_id 与 position）
    const items = board.value.columns.map((c) => ({
        column_id: c.id,
        ids: c.cards.map((card) => card.id),
    }));

    // 序列化移动请求：连续拖拽时让上一个请求完成后再发下一个，
    // 避免被 Inertia 中断（interrupted）导致服务端漏记、随后刷新弹回原列
    moveChain = moveChain.then(
        () =>
            new Promise<void>((resolve) => {
                router.post(
                    cardMove.url(),
                    { items },
                    {
                        preserveScroll: true,
                        async: true, // 走异步流，不被"加卡片/保存"等同步访问打断
                        // 用 onFinish 而非 onSuccess：204 响应不触发 onSuccess（走 handleNonInertiaResponse），
                        // 但 onFinish 在所有路径（含 204/取消/失败）都会调用，确保串行链不卡死
                        onFinish: () => resolve(),
                        onError: (errors) => {
                            // 移动失败：保留乐观结果，不再 router.reload()
                            // （避免用服务端旧状态覆盖本地结果造成"弹回"假象）
                            console.error('[Reflow] 卡片移动失败：', errors);
                            resolve();
                        },
                    },
                );
            }),
    );
}

/* ---------------- 新建卡片 ---------------- */
const newCardTitles = ref<Record<number, string>>({});
function addCard(colId: number) {
    const title = (newCardTitles.value[colId] ?? '').trim();
    if (!title) return;
    router.post(
        cardStore.url(),
        { column_id: colId, title },
        {
            preserveScroll: true,
            onSuccess: () => {
                newCardTitles.value[colId] = '';
            },
        },
    );
}

/* ---------------- 新建列 ---------------- */
const addingColumn = ref(false);
const newColumnName = ref('');
function addColumn() {
    const name = newColumnName.value.trim();
    if (!name) return;
    router.post(
        columnStore.url(),
        { board_id: board.value.id, name },
        {
            preserveScroll: true,
            onSuccess: () => {
                newColumnName.value = '';
                addingColumn.value = false;
            },
        },
    );
}

/* ---------------- 列：重命名 / 删除 ---------------- */
const editingColumnId = ref<number | null>(null);
const editingColumnName = ref('');
function startEditColumn(col: Column) {
    editingColumnId.value = col.id;
    editingColumnName.value = col.name;
}
function saveEditColumn(col: Column) {
    const name = editingColumnName.value.trim();
    if (name && name !== col.name) {
        router.patch(columnUpdate.url(col.id), { name }, { preserveScroll: true });
    }
    editingColumnId.value = null;
}
function deleteColumn(col: Column) {
    router.delete(columnDestroy.url(col.id), { preserveScroll: true });
}

/* ---------------- 看板：重命名 / 删除 ---------------- */
const boardDialogOpen = ref(false);
const activityDialogOpen = ref(false);
const boardForm = useForm({ name: props.board.name });
function openBoardDialog() {
    boardForm.name = board.value.name;
    boardDialogOpen.value = true;
}
function saveBoard() {
    boardForm.patch(boardsUpdate.url(board.value.slug), {
        preserveScroll: true,
        onSuccess: () => (boardDialogOpen.value = false),
    });
}
function deleteBoard() {
    router.delete(boardsDestroy.url(board.value.slug), { preserveScroll: false });
}

/* ---------------- 卡片对话框 ---------------- */
const dialogOpen = ref(false);
const selectedCard = ref<Card | null>(null);
const cardForm = useForm({
    title: '',
    description: '',
    assignee_id: null as number | null,
    due_date: '',
    priority: 'medium' as 'low' | 'medium' | 'high',
    color: '' as string,
    labels: [] as number[],
});

// 子任务新增输入、标签新建输入
const newSubtask = ref('');
const newLabelName = ref('');
const newLabelColor = ref('#5C5CD6');
const labelBusy = ref(false);

function openCard(card: Card) {
    selectedCard.value = card;
    cardForm.title = card.title;
    cardForm.description = card.description ?? '';
    cardForm.assignee_id = card.assignee_id;
    cardForm.due_date = card.due_date ?? '';
    cardForm.priority = card.priority ?? 'medium';
    cardForm.color = card.color ?? '';
    cardForm.labels = card.labels.map((l) => l.id);
    dialogOpen.value = true;
}

// 编辑指示：进入/离开卡片标题或描述时广播给其他人
function focusCard() {
    if (selectedCard.value) {
        whisper('card:focus', { cardId: selectedCard.value.id, id: auth.user.id, name: auth.user.name });
    }
}
function blurCard() {
    if (selectedCard.value) {
        whisper('card:blur', { cardId: selectedCard.value.id, id: auth.user.id, name: auth.user.name });
    }
}
function saveCard() {
    if (!selectedCard.value) return;
    cardForm.patch(cardUpdate.url(selectedCard.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            // 用服务端最新数据刷新选中卡片
            const fresh = findCard(selectedCard.value!.id);
            if (fresh) selectedCard.value = fresh;
        },
    });
}
function deleteCard() {
    if (!selectedCard.value) return;
    const id = selectedCard.value.id;
    router.delete(cardDestroy.url(id), {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            selectedCard.value = null;
        },
    });
}

/* ---------------- 标签 / 子任务 ---------------- */
function toggleLabel(id: number): void {
    const i = cardForm.labels.indexOf(id);
    if (i === -1) cardForm.labels.push(id);
    else cardForm.labels.splice(i, 1);
}

function createLabel(): void {
    const name = newLabelName.value.trim();
    if (!name || labelBusy.value) return;
    labelBusy.value = true;
    router.post(
        `/boards/${board.value.slug}/labels`,
        { name, color: newLabelColor.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                newLabelName.value = '';
            },
            onFinish: () => {
                labelBusy.value = false;
            },
        },
    );
}

function addSubtask(): void {
    const title = newSubtask.value.trim();
    if (!title || !selectedCard.value) return;
    router.post(
        '/subtasks',
        { card_id: selectedCard.value.id, title },
        { preserveScroll: true, onSuccess: () => (newSubtask.value = '') },
    );
}

function toggleSubtask(st: { id: number; is_complete: boolean }): void {
    router.patch(`/subtasks/${st.id}`, { is_complete: !st.is_complete }, { preserveScroll: true });
}

function deleteSubtask(st: { id: number }): void {
    router.delete(`/subtasks/${st.id}`, { preserveScroll: true });
}

/* ---------------- 评论 ---------------- */
const commentForm = useForm({ card_id: 0, body: '' });
function addComment() {
    if (!selectedCard.value) return;
    const cardId = selectedCard.value.id;
    const body = commentForm.body.trim();
    if (!body) return;

    // 乐观追加：204 响应不会触发 onSuccess，故必须同步处理 UI（与 onColumnDrop 同理）
    const optimistic: Comment = {
        id: Date.now(),
        body,
        user: { id: auth.user.id, name: auth.user.name },
        created_at: new Date().toISOString(),
        pending: true,
    };
    selectedCard.value.comments = [...selectedCard.value.comments, optimistic];

    commentForm.card_id = cardId;
    commentForm.post(commentStore.url(), {
        preserveScroll: true,
        onError: () => {
            // 仅失败时回滚乐观更新
            if (selectedCard.value && selectedCard.value.id === cardId) {
                selectedCard.value.comments = selectedCard.value.comments.filter((x) => x.id !== optimistic.id);
            }
        },
    });
    commentForm.reset('body'); // 发送已在上一行序列化，这里立即清空输入框
}
function deleteComment(commentId: number) {
    if (!selectedCard.value) return;
    const cardId = selectedCard.value.id;
    const c = selectedCard.value;
    const idx = c.comments.findIndex((x) => x.id === commentId);
    if (idx === -1) return;

    const target = c.comments[idx];
    // 刚乐观发布的评论尚未拿到服务端真实 id，直接本地移除（避免 DELETE 命中占位 id 触发 404）
    if (target.pending) {
        c.comments = c.comments.filter((x) => x.id !== commentId);
        return;
    }

    // 其余评论：204 不触发 onSuccess，同步乐观移除
    const removed = target;
    c.comments = c.comments.filter((x) => x.id !== commentId);

    router.delete(commentDestroy.url(commentId), {
        preserveScroll: true,
        onError: () => {
            // 仅失败时回滚
            if (selectedCard.value && selectedCard.value.id === cardId && !selectedCard.value.comments.some((x) => x.id === commentId)) {
                const cur = selectedCard.value.comments;
                selectedCard.value.comments = [...cur.slice(0, idx), removed, ...cur.slice(idx)];
            }
        },
    });
}

function formatDate(d: string): string {
    return d ? d.slice(0, 10) : '';
}

/* ---------------- 成员管理 ---------------- */
const membersDialogOpen = ref(false);
const memberForm = useForm({ email: '', role: 'member' as 'member' | 'admin' });

function addMember() {
    if (!memberForm.email.trim()) return;
    memberForm.post(`/boards/${board.value.slug}/members`, {
        preserveScroll: true,
        onSuccess: () => memberForm.reset(),
    });
}

function changeRole(memberId: number, role: string) {
    router.patch(`/boards/${board.value.slug}/members/${memberId}`, { role }, { preserveScroll: true });
}

// 移出成员确认弹窗
const removeTarget = ref<Member | null>(null);
const removeDialogOpen = ref(false);

function removeMember(member: Member) {
    removeTarget.value = member;
    removeDialogOpen.value = true;
}
function confirmRemoveMember() {
    const m = removeTarget.value;
    if (!m) return;
    router.delete(`/boards/${board.value.slug}/members/${m.id}`, {
        preserveScroll: true,
        onFinish: () => {
            removeDialogOpen.value = false;
            removeTarget.value = null;
        },
    });
}
function initials(name: string): string {
    return name.slice(0, 1).toUpperCase();
}

// 列色点：按索引取原型色板（indigo / teal / amber / rose / slate）
const COLUMN_DOT_COLORS = ['#5B5BD6', '#0EA5A4', '#E8920C', '#E5484D', '#64748B'];
// 卡片强调色预设
const CARD_COLORS = ['#5C5CD6', '#E5484D', '#E8920C', '#0EA5A4', '#16A34A', '#64748B'];
function columnDotColor(i: number): string {
    return COLUMN_DOT_COLORS[i % COLUMN_DOT_COLORS.length];
}

// 截止日临近（≤2 天）高亮
function dueSoon(d: string): boolean {
    if (!d) return false;
    const t = (new Date(d).getTime() - Date.now()) / 86400000;
    return t <= 2;
}

// 优先级徽章配色
function priorityClass(p: string): string {
    if (p === 'high') return 'bg-red-100 text-red-700';
    if (p === 'low') return 'bg-sky-100 text-sky-700';
    return 'bg-secondary text-muted-foreground';
}

// 活动文案
function activityText(a: { type: string; payload?: Record<string, unknown> }): string {
    const p = a.payload || {};
    const title = (p.title as string) ?? '';
    switch (a.type) {
        case 'card.created':
            return t('board.activityCreated', { title });
        case 'card.moved':
            return t('board.activityMoved', { title });
        case 'card.updated':
            return t('board.activityUpdated', { title });
        case 'card.deleted':
            return t('board.activityDeleted', { title });
        default:
            return a.type;
    }
}
</script>

<template>
    <Head :title="board.name" />

    <div class="relative flex h-full flex-col">
        <!-- 加载进度条 -->
        <div v-if="isLoading" class="absolute inset-x-0 top-0 z-40 h-0.5 bg-primary/70 motion-safe:animate-pulse"></div>

        <!-- 头部 -->
        <header class="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
            <div class="flex min-w-0 items-center gap-2">
                <Link :href="boardsIndex().url">
                    <Button variant="ghost" size="icon" class="size-8">
                        <ArrowLeft class="size-4" />
                    </Button>
                </Link>
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-semibold">{{ board.name }}</h1>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ t('board.memberSubtitle', { count: board.members.length, online: onlineMembers.length }) }}</p>
                </div>

                <!-- 在线成员 + 成员管理入口 -->
                <div class="hidden items-center gap-2 sm:flex">
                    <div
                        v-if="onlineMembers.length"
                        class="flex -space-x-1.5"
                        :title="t('members.onlineCount', { count: onlineMembers.length })"
                    >
                        <span
                            v-for="m in onlineMembers.slice(0, 4)"
                            :key="'on-' + m.id"
                            class="inline-flex size-5 items-center justify-center rounded-full bg-green-500 text-[9px] font-semibold text-white ring-2 ring-background"
                        >{{ initials(m.name) }}</span>
                        <span
                            class="inline-flex size-5 items-center justify-center rounded-full bg-green-500 text-[9px] font-semibold text-white ring-2 ring-background"
                        >{{ onlineMembers.length }}</span>
                    </div>
                    <span
                        v-if="connected"
                        class="inline-flex items-center gap-1 text-xs text-green-600"
                        :title="t('realtime.connected')"
                    >
                        <span class="size-2 rounded-full bg-green-500"></span>{{ t('realtime.status') }}
                    </span>
                    <Button
                        v-if="canManageMembers"
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1"
                        @click="membersDialogOpen = true"
                    >
                        <UserPlus class="size-4" /> {{ t('members.manage') }}
                    </Button>
                </div>
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" size="icon" class="size-8">
                        <MoreVertical class="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem as-child>
                        <Link :href="boardsRetro(board.slug).url">
                            <BarChart3 class="mr-2 size-4" /> {{ t('board.retroDashboard') }}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="activityDialogOpen = true">
                        <History class="mr-2 size-4" /> {{ t('board.activity') }}
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="openBoardDialog">
                        <Pencil class="mr-2 size-4" /> {{ t('board.rename') }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator v-if="isOwner()" />
                    <DropdownMenuItem v-if="isOwner()" class="text-destructive focus:text-destructive" @click="deleteBoard">
                        <Trash2 class="mr-2 size-4" /> {{ t('board.delete') }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>

        <!-- 空看板引导 -->
        <div v-if="isEmptyBoard()" class="flex flex-1 flex-col items-center justify-center gap-3 p-8 text-center">
            <div class="rounded-full bg-muted p-4">
                <Columns3 class="size-8 text-muted-foreground" />
            </div>
            <p class="text-lg font-medium">{{ t('board.emptyTitle') }}</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                {{ t('board.emptyDesc') }}
            </p>

            <!-- 未开始添加：引导按钮 -->
            <Button v-if="!addingColumn" @click="addingColumn = true">
                <Plus class="mr-1 size-4" /> {{ t('board.createFirstColumn') }}
            </Button>

            <!-- 开始添加：内联输入框（保持在空状态分支内，避免被 v-else 隐藏） -->
            <div v-else class="w-full max-w-xs rounded-xl border border-border bg-card p-3 text-left">
                <Input
                    v-model="newColumnName"
                    class="mb-2 h-8 text-sm"
                    :placeholder="t('board.columnNamePlaceholder')"
                    autofocus
                    @keyup.enter="addColumn"
                />
                <div class="flex gap-2">
                    <Button size="sm" class="flex-1" @click="addColumn">{{ t('board.add') }}</Button>
                    <Button size="sm" variant="ghost" @click="addingColumn = false">{{ t('board.cancel') }}</Button>
                </div>
            </div>
        </div>

        <!-- 看板列 -->
        <div v-else class="flex flex-1 gap-4 overflow-x-auto p-4 max-[640px]:snap-x max-[640px]:snap-mandatory max-[640px]:gap-3 max-[640px]:px-4 max-[640px]:pb-20" :class="{ 'opacity-60 pointer-events-none': isLoading }">
            <div
                v-for="(column, idx) in board.columns"
                :key="column.id"
                class="flex w-72 shrink-0 flex-col rounded-[14px] border border-border bg-secondary max-[640px]:w-[calc(100vw-2rem)] max-[640px]:shrink-0 max-[640px]:snap-center"
                @dragover.prevent="onColumnDragOver($event)"
                @drop.prevent="onColumnDrop(column.id)"
            >
                <!-- 列头 -->
                <div class="flex items-center justify-between gap-2 px-3 py-2">
                    <div class="flex flex-1 items-center gap-2">
                        <span
                            class="size-2.5 shrink-0 rounded-full"
                            :style="{ backgroundColor: columnDotColor(idx) }"
                        ></span>
                        <input
                            v-if="editingColumnId === column.id"
                            v-model="editingColumnName"
                            class="w-full rounded border border-input bg-background px-1.5 py-0.5 text-sm font-semibold focus:outline-none focus:ring-1 focus:ring-ring"
                            @keyup.enter="saveEditColumn(column)"
                            @blur="saveEditColumn(column)"
                        />
                        <h2 v-else class="min-w-0 truncate text-base font-semibold">{{ column.name }}</h2>
                        <span
                            v-if="column.wip_limit"
                            class="shrink-0 rounded border px-1.5 py-0.5 text-xs"
                            :class="column.cards.length >= column.wip_limit ? 'border-amber-300 bg-amber-50 text-amber-600' : 'border-border bg-card text-muted-foreground'"
                            >{{ column.cards.length }}/{{ column.wip_limit }}</span
                        >
                        <span v-if="!column.wip_limit" class="shrink-0 text-xs font-medium text-muted-foreground">{{ column.cards.length }}</span>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="icon" class="size-7">
                                <MoreVertical class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="startEditColumn(column)">
                                <Pencil class="mr-2 size-4" /> {{ t('board.renameColumn') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem class="text-destructive focus:text-destructive" @click="deleteColumn(column)">
                                <Trash2 class="mr-2 size-4" /> {{ t('board.deleteColumn') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <!-- 卡片列表 -->
                <div class="flex flex-1 flex-col gap-2 overflow-y-auto px-3 pb-2">
                    <div
                        v-for="(card, index) in column.cards"
                        :key="card.id"
                        draggable="true"
                        class="group relative cursor-pointer rounded-[10px] border border-border bg-card p-3 text-sm shadow-sm transition hover:border-primary/40 hover:shadow"
                        :class="{ 'ring-2 ring-primary/40': overColId === column.id && overIndex === index }"
                        :style="card.color ? { borderLeftColor: card.color, borderLeftWidth: '4px' } : {}"
                        @click="openCard(card)"
                        @dragstart="onCardDragStart($event, card, column.id)"
                        @dragend="onDragEnd"
                        @dragover.prevent="onCardDragOver($event, column.id, index)"
                    >
                        <!-- 编辑中指示：他人正在编辑此卡 -->
                        <span
                            v-if="editing[card.id]"
                            class="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary"
                            :title="editing[card.id].name + ' 正在编辑'"
                        >
                            <span class="size-1.5 animate-pulse rounded-full bg-primary"></span>
                            {{ editing[card.id].name }}
                        </span>

                        <div class="flex items-start gap-2">
                            <GripVertical class="mt-0.5 size-4 shrink-0 text-muted-foreground/50" />
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold leading-snug">{{ card.title }}</p>
                                <div v-if="card.description" class="mt-1 line-clamp-2 text-[13px] text-muted-foreground">
                                    {{ card.description }}
                                </div>
                                <!-- 标签 chips -->
                                <div v-if="card.labels.length" class="mt-2 flex flex-wrap gap-1">
                                    <span
                                        v-for="label in card.labels"
                                        :key="label.id"
                                        class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                        :style="{ backgroundColor: label.color + '22', color: label.color }"
                                    >
                                        {{ label.name }}
                                    </span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    <span v-if="card.priority && card.priority !== 'medium'" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5" :class="priorityClass(card.priority)">
                                        <Flag class="size-3" /> {{ t('board.priority.' + card.priority) }}
                                    </span>
                                    <span v-if="card.due_date" class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs" :class="dueSoon(card.due_date) ? 'bg-amber-100 text-amber-700' : 'bg-secondary text-muted-foreground'">
                                        <Calendar class="size-3" /> {{ formatDate(card.due_date) }}
                                    </span>
                                    <span v-if="card.assignee" class="inline-flex items-center gap-1">
                                        <span class="flex size-5 items-center justify-center rounded-full bg-primary/10 text-[10px] font-semibold text-primary">{{ initials(card.assignee.name) }}</span>
                                        {{ card.assignee.name }}
                                    </span>
                                    <span v-if="card.comments.length" class="inline-flex items-center gap-1">
                                        <MessageSquare class="size-3.5" /> {{ card.comments.length }}
                                    </span>
                                    <span v-if="card.subtasks.length" class="inline-flex items-center gap-1">
                                        <CheckSquare class="size-3.5" />
                                        {{ card.subtasks.filter((s) => s.is_complete).length }}/{{ card.subtasks.length }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="column.cards.length === 0" class="px-1 py-3 text-center text-xs text-muted-foreground">
                        {{ t('board.dropHere') }}
                    </p>
                </div>

                <!-- 新建卡片 -->
                <div class="p-3 pt-0">
                    <div class="flex gap-2">
                        <Input
                            v-model="newCardTitles[column.id]"
                            class="h-8 text-sm"
                            :placeholder="t('board.cardPlaceholder')"
                            @keyup.enter="addCard(column.id)"
                        />
                        <Button size="icon" variant="outline" class="size-8 shrink-0" @click="addCard(column.id)">
                            <Plus class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- 新建列 -->
            <div class="w-72 shrink-0 max-[640px]:w-[calc(100vw-2rem)] max-[640px]:shrink-0 max-[640px]:snap-center">
                <div v-if="!addingColumn" class="rounded-xl border border-dashed border-border p-3">
                    <Button variant="ghost" class="w-full justify-start text-muted-foreground" @click="addingColumn = true">
                        <Plus class="mr-1 size-4" /> {{ t('board.addColumn') }}
                    </Button>
                </div>
                <div v-else class="rounded-xl border border-border bg-secondary p-3">
                    <Input
                        v-model="newColumnName"
                        class="mb-2 h-8 text-sm"
                        :placeholder="t('board.columnNamePlaceholder')"
                        autofocus
                        @keyup.enter="addColumn"
                    />
                    <div class="flex gap-2">
                        <Button size="sm" class="flex-1" @click="addColumn">{{ t('board.add') }}</Button>
                        <Button size="sm" variant="ghost" @click="addingColumn = false">{{ t('board.cancel') }}</Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 移动端底部操作条 -->
        <div
            class="fixed inset-x-0 bottom-0 z-30 hidden items-center gap-2 border-t border-border bg-background/95 p-3 backdrop-blur max-[640px]:flex"
        >
            <Button variant="outline" class="flex-1" @click="membersDialogOpen = true">
                <UserPlus class="mr-1 size-4" /> {{ t('members.manage') }}
            </Button>
            <Button class="flex-1" @click="addingColumn = true">
                <Plus class="mr-1 size-4" /> {{ t('board.addColumn') }}
            </Button>
        </div>
    </div>

    <!-- 看板重命名对话框 -->
    <Dialog v-model:open="boardDialogOpen">
        <DialogContent class="reflow-dialog">
            <DialogHeader>
                <DialogTitle>{{ t('board.renameDialogTitle') }}</DialogTitle>
                <DialogDescription>{{ t('board.renameDialogDesc') }}</DialogDescription>
            </DialogHeader>
            <div class="grid gap-2">
                <Label for="board-name">{{ t('board.nameLabel') }}</Label>
                <Input id="board-name" v-model="boardForm.name" />
                <InputError :message="boardForm.errors.name" />
            </div>
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="secondary">{{ t('board.cancel') }}</Button>
                </DialogClose>
                <Button :disabled="boardForm.processing" @click="saveBoard">{{ t('board.save') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- 卡片详情对话框 -->
    <Dialog v-model:open="dialogOpen">
        <DialogContent v-if="selectedCard" class="reflow-dialog sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('board.cardDetail') }}</DialogTitle>
                <DialogDescription>{{ t('board.cardDetailDesc') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="grid gap-2">
                    <Label for="card-title">{{ t('board.cardTitle') }}</Label>
                    <Input id="card-title" v-model="cardForm.title" @focus="focusCard" @blur="blurCard" />
                    <InputError :message="cardForm.errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="card-desc">{{ t('board.cardDesc') }}</Label>
                    <textarea
                        id="card-desc"
                        v-model="cardForm.description"
                        rows="3"
                        class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        :placeholder="t('board.cardDescPlaceholder')"
                        @focus="focusCard"
                        @blur="blurCard"
                    ></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-2">
                        <Label for="card-assignee">{{ t('board.assignee') }}</Label>
                        <select
                            id="card-assignee"
                            v-model="cardForm.assignee_id"
                            class="h-9 rounded-md border border-input bg-background px-2 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option :value="null">{{ t('board.unassigned') }}</option>
                            <option v-for="m in board.members" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="card-due">{{ t('board.dueDate') }}</Label>
                        <Input id="card-due" v-model="cardForm.due_date" type="date" />
                    </div>
                </div>

                <!-- 优先级 -->
                <div class="grid gap-2">
                    <Label>{{ t('board.priority.label') }}</Label>
                    <div class="flex gap-2">
                        <button
                            v-for="p in (['low', 'medium', 'high'] as const)"
                            :key="p"
                            type="button"
                            class="flex-1 rounded-md border px-2 py-1.5 text-sm font-medium transition"
                            :class="cardForm.priority === p ? priorityClass(p) + ' ring-1 ring-primary/40' : 'border-input hover:border-primary/40'"
                            @click="cardForm.priority = p"
                        >
                            {{ t('board.priority.' + p) }}
                        </button>
                    </div>
                </div>

                <!-- 强调色 -->
                <div class="grid gap-2">
                    <Label>{{ t('board.color') }}</Label>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="c in CARD_COLORS"
                            :key="c"
                            type="button"
                            class="size-6 rounded-full border-2 transition"
                            :class="cardForm.color === c ? 'border-primary' : 'border-transparent'"
                            :style="{ backgroundColor: c }"
                            @click="cardForm.color = c"
                        ></button>
                        <button
                            type="button"
                            class="size-6 rounded-full border-2 text-xs transition"
                            :class="!cardForm.color ? 'border-primary' : 'border-input'"
                            @click="cardForm.color = ''"
                        >
                            ∅
                        </button>
                    </div>
                </div>

                <!-- 标签 -->
                <div class="grid gap-2">
                    <Label>{{ t('board.labels') }}</Label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="label in board.labels"
                            :key="label.id"
                            type="button"
                            class="rounded-full px-2 py-0.5 text-xs font-medium transition"
                            :style="{
                                backgroundColor: cardForm.labels.includes(label.id) ? label.color : label.color + '22',
                                color: label.color,
                                outline: cardForm.labels.includes(label.id) ? '2px solid ' + label.color : '2px solid transparent',
                            }"
                            @click="toggleLabel(label.id)"
                        >
                            {{ label.name }}
                        </button>
                        <span v-if="board.labels.length === 0" class="text-xs text-muted-foreground">{{ t('board.noLabels') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="newLabelName"
                            class="h-8 flex-1 rounded-md border border-input bg-background px-2 text-xs focus:outline-none focus:ring-1 focus:ring-ring"
                            :placeholder="t('board.newLabelName')"
                            @keyup.enter="createLabel"
                        />
                        <input v-model="newLabelColor" type="color" class="size-8 rounded border border-input" />
                        <Button size="sm" :disabled="labelBusy || !newLabelName.trim()" @click="createLabel">{{ t('board.addLabel') }}</Button>
                    </div>
                </div>

                <!-- 子任务 -->
                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label>{{ t('board.subtasks') }}</Label>
                        <span v-if="selectedCard.subtasks.length" class="text-xs text-muted-foreground">
                            {{ selectedCard.subtasks.filter((s) => s.is_complete).length }}/{{ selectedCard.subtasks.length }}
                        </span>
                    </div>
                    <div class="space-y-1.5">
                        <label
                            v-for="st in selectedCard.subtasks"
                            :key="st.id"
                            class="flex items-center gap-2 rounded-md bg-muted/40 px-2 py-1.5 text-sm"
                        >
                            <input
                                type="checkbox"
                                :checked="st.is_complete"
                                class="size-4 rounded border-input"
                                @change="toggleSubtask(st)"
                            />
                            <span :class="st.is_complete ? 'text-muted-foreground line-through' : ''" class="flex-1">{{ st.title }}</span>
                            <button type="button" class="text-muted-foreground hover:text-destructive" @click="deleteSubtask(st)">
                                <Trash2 class="size-3.5" />
                            </button>
                        </label>
                        <p v-if="selectedCard.subtasks.length === 0" class="text-xs text-muted-foreground">{{ t('board.noSubtasks') }}</p>
                    </div>
                    <div class="flex gap-2">
                        <Input v-model="newSubtask" class="h-8 text-sm" :placeholder="t('board.subtaskPlaceholder')" @keyup.enter="addSubtask" />
                        <Button size="sm" :disabled="!newSubtask.trim()" @click="addSubtask">{{ t('board.add') }}</Button>
                    </div>
                </div>

                <!-- 评论 -->
                <div class="border-t pt-3">
                    <h4 class="mb-2 text-sm font-medium">{{ t('board.comments') }} ({{ selectedCard.comments.length }})</h4>
                    <div class="max-h-40 space-y-2 overflow-y-auto">
                        <div
                            v-for="comment in selectedCard.comments"
                            :key="comment.id"
                            class="group flex items-start justify-between gap-2 rounded-md bg-muted/50 p-2 text-sm"
                        >
                            <div>
                                <p class="text-xs text-muted-foreground">{{ comment.user?.name ?? t('board.unknownUser') }} · {{ formatDate(comment.created_at) }}</p>
                                <p class="whitespace-pre-wrap">{{ comment.body }}</p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-6 opacity-0 group-hover:opacity-100"
                                @click="deleteComment(comment.id)"
                            >
                                <Trash2 class="size-3.5 text-destructive" />
                            </Button>
                        </div>
                        <p v-if="selectedCard.comments.length === 0" class="text-xs text-muted-foreground">{{ t('board.noComments') }}</p>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <Input v-model="commentForm.body" class="h-8 text-sm" :placeholder="t('board.commentPlaceholder')" @keyup.enter="addComment" />
                        <Button size="sm" :disabled="commentForm.processing" @click="addComment">{{ t('board.send') }}</Button>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="destructive" :disabled="cardForm.processing" @click="deleteCard">{{ t('board.deleteCard') }}</Button>
                <Button :disabled="cardForm.processing" @click="saveCard">{{ t('board.save') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- 活动面板 -->
    <Dialog v-model:open="activityDialogOpen">
        <DialogContent class="reflow-dialog sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('board.activity') }}</DialogTitle>
                <DialogDescription>{{ t('board.activityDesc') }}</DialogDescription>
            </DialogHeader>
            <div class="max-h-96 space-y-3 overflow-y-auto">
                <div
                    v-for="(a, i) in board.activities"
                    :key="a.id ?? i"
                    class="flex items-start gap-3 text-sm"
                >
                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-primary/60"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-foreground">
                            <span class="font-medium">{{ a.user?.name ?? t('board.unknownUser') }}</span>
                            {{ activityText(a) }}
                        </p>
                        <p class="text-xs text-muted-foreground">{{ formatDate(a.created_at) }}</p>
                    </div>
                </div>
                <p v-if="board.activities.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                    {{ t('board.noActivity') }}
                </p>
            </div>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="secondary">{{ t('board.close') }}</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- 成员管理对话框 -->
    <Dialog v-model:open="membersDialogOpen">
        <DialogContent class="reflow-dialog gap-0 overflow-hidden border p-0 shadow-lg sm:max-w-md" :show-close-button="false">
            <!-- 头部：图标 + 标题 -->
            <DialogHeader class="space-y-0 border-b border-border bg-secondary px-5 py-4 text-left">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                        <Users class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <DialogTitle class="text-base font-semibold text-foreground">{{ t('members.title') }}</DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground">{{ t('members.description') }}</DialogDescription>
                    </div>
                    <span class="ml-auto flex h-6 shrink-0 items-center rounded-full bg-background px-2 text-xs font-medium text-muted-foreground">
                        {{ board.members.length }}
                    </span>
                </div>
            </DialogHeader>

            <div class="space-y-4 p-5">
                <!-- 邀请成员 -->
                <div v-if="canManageMembers" class="rounded-lg border border-dashed border-border bg-secondary/50 p-3">
                    <Label for="member-email" class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <Mail class="size-3.5" /> {{ t('members.inviteByEmail') }}
                    </Label>
                    <div class="flex gap-2">
                        <Input
                            id="member-email"
                            v-model="memberForm.email"
                            type="email"
                            class="h-9 flex-1 border-input text-sm focus-visible:border-primary focus-visible:ring-0"
                            :placeholder="t('members.emailPlaceholder')"
                            @keyup.enter="addMember"
                        />
                        <select
                            v-model="memberForm.role"
                            class="h-9 rounded-md border border-input bg-background px-2 text-sm font-medium focus:border-primary focus:outline-none"
                        >
                            <option value="member">{{ t('members.role.member') }}</option>
                            <option value="admin">{{ t('members.role.admin') }}</option>
                        </select>
                        <Button
                            size="sm"
                            :disabled="memberForm.processing"
                            class="h-9 bg-primary px-3 font-medium text-primary-foreground hover:bg-primary/90"
                            @click="addMember"
                        >
                            {{ t('members.add') }}
                        </Button>
                    </div>
                    <InputError :message="memberForm.errors.email" class="mt-1.5" />
                    <p class="mt-1.5 text-xs text-muted-foreground">{{ t('members.inviteHint') }}</p>
                </div>

                <!-- 成员列表 -->
                <div class="max-h-72 space-y-2 overflow-y-auto pr-0.5">
                    <div
                        v-for="m in board.members"
                        :key="m.id"
                        class="flex flex-col gap-2 rounded-lg border border-border bg-card px-3 py-2.5 transition-colors hover:border-primary/40 sm:flex-row sm:items-center"
                    >
                        <div class="relative shrink-0">
                            <Avatar class="size-9 rounded-full border border-border">
                                <AvatarFallback
                                    class="rounded-none text-xs font-semibold"
                                    :class="memberRole(m) === 'owner' ? 'bg-amber-100 text-amber-700' : memberRole(m) === 'admin' ? 'bg-violet-100 text-violet-700' : 'bg-secondary text-secondary-foreground'"
                                >{{ initials(m.name) }}</AvatarFallback>
                            </Avatar>
                            <span
                                v-if="onlineMembers.some((o) => o.id === m.id)"
                                class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-card bg-green-500"
                                :title="t('members.online')"
                            ></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-foreground">
                                <span class="truncate">{{ m.name }}</span>
                                <span v-if="m.id === auth.user.id" class="rounded border border-border px-1 text-[10px] font-medium text-muted-foreground">{{ t('members.you') }}</span>
                                <span
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full border px-1.5 py-px text-[11px] font-medium"
                                    :class="memberRole(m) === 'owner' ? 'border-amber-200 bg-amber-100 text-amber-700' : memberRole(m) === 'admin' ? 'border-violet-200 bg-violet-100 text-violet-700' : 'border-border bg-secondary text-muted-foreground'"
                                >
                                    <Crown v-if="memberRole(m) === 'owner'" class="size-3" />
                                    <ShieldCheck v-else-if="memberRole(m) === 'admin'" class="size-3" />
                                    {{ roleLabel(memberRole(m)) }}
                                </span>
                            </p>
                        </div>

                        <!-- 角色切换 / 移除（仅管理者，且不能改/移除拥有者） -->
                        <template v-if="canManageMembers && memberRole(m) !== 'owner'">
                            <div class="flex items-center gap-1.5">
                                <select
                                    :value="memberRole(m)"
                                    class="h-8 rounded-md border border-input bg-background px-1.5 text-xs font-medium focus:border-primary focus:outline-none"
                                    @change="changeRole(m.id, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option value="member">{{ t('members.role.member') }}</option>
                                    <option value="admin">{{ t('members.role.admin') }}</option>
                                </select>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 rounded-md text-destructive hover:bg-destructive/10"
                                    :title="t('members.confirmRemoveAction')"
                                    @click="removeMember(m)"
                                >
                                    <UserMinus class="size-4" />
                                </Button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <DialogFooter class="border-t border-border bg-secondary/40 px-5 py-3">
                <DialogClose as-child>
                    <Button variant="secondary" class="font-medium">{{ t('common.close') }}</Button>
                </DialogClose>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- 移出成员确认弹窗 -->
    <AlertDialog v-model:open="removeDialogOpen">
        <AlertDialogContent class="reflow-dialog">
            <!-- 柔和处理头部 -->
            <div class="flex items-center gap-4 border-b border-border px-6 py-5">
                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                    <AlertTriangle class="size-6" />
                </span>
                <div class="min-w-0">
                    <AlertDialogTitle class="text-foreground">{{ t('members.confirmRemoveTitle') }}</AlertDialogTitle>
                    <p class="mt-0.5 text-xs font-medium text-muted-foreground">{{ removeTarget?.name }}</p>
                </div>
            </div>

            <AlertDialogHeader class="px-6 pt-1">
                <AlertDialogDescription>
                    {{ t('members.confirmRemoveDesc', { name: removeTarget?.name, board: board.name }) }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <AlertDialogFooter class="border-t border-border bg-secondary/40 px-6 py-4">
                <AlertDialogCancel class="shadow-sm">{{ t('common.cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-white hover:bg-destructive/90"
                    @click="confirmRemoveMember"
                >
                    <UserMinus class="size-4" /> {{ t('members.confirmRemoveAction') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
