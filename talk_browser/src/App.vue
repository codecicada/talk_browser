<template>
	<NcContent app-name="talk_browser">
		<!-- Left navigation: conversation picker -->
		<NcAppNavigation>
			<template #list>
				<ConversationPicker
					v-model="selectedToken"
					:conversations="filteredConversations"
					:loading="conversationsLoading"
				/>
			</template>
			<template #footer>
				<div class="app__settings-trigger">
					<NcButton alignment="start"
						variant="tertiary"
						wide
						@click="showSettingsModal = true">
						<template #icon>
							<span class="icon-settings-dark" aria-hidden="true" />
						</template>
						{{ t('talk_browser', 'Settings') }}
					</NcButton>
				</div>
			</template>
		</NcAppNavigation>

		<!-- Settings modal -->
		<NcDialog :open="showSettingsModal"
			:name="t('talk_browser', 'Settings')"
			size="small"
			:close-on-click-outside="true"
			@update:open="showSettingsModal = $event">
			<SettingsPanel
				:hide-group-conversations="hideGroupConversations"
				@update:hide-group-conversations="onUpdateHideGroupConversations"
			/>
		</NcDialog>

		<!-- Main content area -->
		<NcAppContent>
			<!-- Loading conversations -->
			<div v-if="conversationsLoading" class="app__loading" role="status" aria-live="polite">
				<NcLoadingIcon :size="48" aria-hidden="true" />
				<p>{{ t('talk_browser', 'Loading conversations…') }}</p>
			</div>

			<!-- Error loading conversations -->
			<NcEmptyContent
				v-else-if="conversationsError"
				:name="t('talk_browser', 'Could not load conversations')"
				:description="t('talk_browser', 'There was a problem connecting to Nextcloud Talk. Please check your connection and try again.')"
			>
				<template #icon>
					<span class="icon-error" aria-hidden="true" />
				</template>
				<template #action>
					<NcButton @click="loadConversations">
						{{ t('talk_browser', 'Retry') }}
					</NcButton>
				</template>
			</NcEmptyContent>

			<!-- No conversation selected -->
			<NcEmptyContent
				v-else-if="!selectedToken"
				:name="t('talk_browser', 'Select a conversation')"
				:description="t('talk_browser', 'Choose a Talk conversation from the sidebar to browse its content')"
			>
				<template #icon>
					<span class="icon-talk" aria-hidden="true" />
				</template>
			</NcEmptyContent>

			<!-- Content browser -->
			<template v-else>
				<div class="app__header">
					<h1 class="app__title">
						{{ selectedConversation?.displayName ?? '' }}
					</h1>
				</div>

				<ContentTabs v-model="activeTab">
					<template #default="{ activeTab: tab, searchQuery, sort }">
						<!-- Overview -->
					<OverviewPanel
					v-if="tab === 'overview'"
					:overview-data="overviewData"
					:loading="overviewLoading"
					:error="overviewError"
					@go-to-tab="activeTab = $event"
					@go-to-item="goToItem"
				/>

						<!-- Item loading error (non-overview tabs) -->
					<NcEmptyContent
						v-else-if="itemsError && !itemsLoading"
						:name="t('talk_browser', 'Could not load content')"
						:description="t('talk_browser', 'There was a problem loading shared items. Please try again.')"
					>
					<template #icon>
							<span class="icon-error" aria-hidden="true" />
						</template>
							<template #action>
								<NcButton @click="loadItems">
									{{ t('talk_browser', 'Retry') }}
								</NcButton>
							</template>
						</NcEmptyContent>

						<!-- Images & Video -->
						<MediaGallery
							v-else-if="tab === 'media'"
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							:sort="sort"
							:highlight-id="highlightId"
							@load-more="loadMoreItems"
						/>

						<!-- Files -->
						<FileList
							v-else-if="tab === 'file'"
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							:sort="sort"
							:highlight-id="highlightId"
							@load-more="loadMoreItems"
						/>

						<!-- Audio -->
						<AudioList
							v-else-if="tab === 'audio'"
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							:sort="sort"
							:is-voice="false"
							:highlight-id="highlightId"
							@load-more="loadMoreItems"
						/>

						<!-- Voice notes -->
						<AudioList
							v-else-if="tab === 'voice'"
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							:sort="sort"
							:is-voice="true"
							:highlight-id="highlightId"
							@load-more="loadMoreItems"
						/>

						<!-- Links -->
						<LinkList
							v-else-if="tab === 'links'"
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							:sort="sort"
							:highlight-id="highlightId"
							@scan-more="scanMoreItems"
						/>

						<!-- Locations / deckcard / other / recording -->
						<GenericList
							v-else
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
							:sort="sort"
							:object-type="tab"
							:highlight-id="highlightId"
							@load-more="loadMoreItems"
						/>
					</template>
				</ContentTabs>
			</template>
		</NcAppContent>
	</NcContent>
</template>

<script>
import {
	NcAppContent,
	NcAppNavigation,
	NcButton,
	NcContent,
	NcDialog,
	NcEmptyContent,
	NcLoadingIcon,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { generateUrl } from '@nextcloud/router'

import ConversationPicker from './components/ConversationPicker.vue'
import ContentTabs from './components/ContentTabs.vue'
import SettingsPanel from './components/SettingsPanel.vue'
import OverviewPanel from './components/OverviewPanel.vue'
import MediaGallery from './components/MediaGallery.vue'
import FileList from './components/FileList.vue'
import AudioList from './components/AudioList.vue'
import LinkList from './components/LinkList.vue'
import GenericList from './components/GenericList.vue'

import { useConversations } from './composables/useConversations.js'
import { useSharedItems } from './composables/useSharedItems.js'
import { fetchShareOverview } from './api/talk.js'
import { TABS, CONVERSATION_TYPE } from './constants.js'

export default {
	name: 'App',

	components: {
		NcAppContent,
		NcAppNavigation,
		NcButton,
		NcContent,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		ConversationPicker,
		ContentTabs,
		SettingsPanel,
		OverviewPanel,
		MediaGallery,
		FileList,
		AudioList,
		LinkList,
		GenericList,
	},

	setup() {
		// ── URL path helpers ─────────────────────────────────────────────────
		const VALID_TABS = ['overview', 'media', 'file', 'audio', 'voice', 'links', 'location', 'other']
		// Base path: e.g. "/apps/talk_browser" (handles subdirectory Nextcloud installs)
		const BASE = generateUrl('/apps/talk_browser').replace(/\/$/, '')

		function parsePath() {
			// Strip the base prefix, then split the remainder
			const rest = window.location.pathname.replace(BASE, '').replace(/^\//, '')
			const [rawToken, rawTab] = rest.split('/')
			return {
				token: rawToken ? decodeURIComponent(rawToken) : null,
				tab: VALID_TABS.includes(rawTab) ? rawTab : null,
			}
		}

		function updatePath(token, tab, push = true) {
			const path = token
				? `${BASE}/${encodeURIComponent(token)}/${tab ?? 'overview'}`
				: `${BASE}/`
			if (window.location.pathname !== path) {
				if (push) {
					window.history.pushState({ token, tab }, '', path)
				} else {
					window.history.replaceState({ token, tab }, '', path)
				}
			}
		}

		// Guard flag: true while the popstate handler is updating reactive state,
		// so the watcher knows to use replaceState instead of pushState.
		let isRestoringFromPopstate = false

		// ── Conversations ────────────────────────────────────────────────────
		const {
			conversations,
			loading: conversationsLoading,
			error: conversationsError,
			selectedToken,
			selectedConversation,
			load: loadConversations,
		} = useConversations()

		// ── Settings: hide group conversations ───────────────────────────────
		const LS_KEY = 'talk_browser.hideGroupConversations'

		function readHideGroupPref() {
			try {
				return localStorage.getItem(LS_KEY) === 'true'
			} catch (e) {
				return false
			}
		}

		const hideGroupConversations = ref(readHideGroupPref())

		const showSettingsModal = ref(false)

		function onUpdateHideGroupConversations(value) {
			hideGroupConversations.value = value
			try {
				localStorage.setItem(LS_KEY, String(value))
			} catch (e) {
				// ignore storage errors
			}
		}

		const filteredConversations = computed(() => {
			if (!hideGroupConversations.value) return conversations.value
			return conversations.value.filter(
				c => c.type !== CONVERSATION_TYPE.GROUP && c.type !== CONVERSATION_TYPE.PUBLIC,
			)
		})

		// ── Active tab ───────────────────────────────────────────────────────
		const activeTab = ref('overview')

		// ── Highlighted item (set when navigating from overview to a tab item) ──
		const highlightId = ref(null)

		// ── Overview data (flat object keyed by type) ────────────────────────
		const overviewData = ref({})
		const overviewLoading = ref(false)
		const overviewError = ref(null)

		// ── Per-tab shared items cache ────────────────────────────────────────
		// One useSharedItems instance per non-overview tab, keyed by objectType.
		// Created once; data persists across tab switches for the session.
		const TAB_OBJECT_TYPES = TABS
			.map(t => t.objectType)
			.filter(t => t && t !== 'overview')

		const tabStores = Object.fromEntries(
			TAB_OBJECT_TYPES.map(objectType => [
				objectType,
				useSharedItems(selectedToken, objectType),
			]),
		)

		// Derived reactive props — computed directly from the active store's refs.
		// computed() re-evaluates whenever activeTab or any store ref changes,
		// with no manual syncing required.
		const activeStore = computed(() => {
			const tab = activeTab.value
			if (tab === 'overview') return null
			return tabStores[tab] ?? null
		})

		const items = computed(() => activeStore.value?.items.value ?? [])
		const itemsLoading = computed(() => activeStore.value?.loading.value ?? false)
		const itemsLoadingMore = computed(() => activeStore.value?.loadingMore.value ?? false)
		const itemsError = computed(() => activeStore.value?.error.value ?? null)
		const itemsHasMore = computed(() => activeStore.value?.hasMore.value ?? false)

		// Trigger load when the active tab changes and token is available
		watch(activeTab, (tab) => {
			if (tab === 'overview') return
			const store = tabStores[tab] ?? null
			if (store && selectedToken.value) {
				store.load()
			}
		})

		function loadItems() {
			activeStore.value?.load()
		}

		function loadMoreItems() {
			activeStore.value?.loadMore()
		}

		function scanMoreItems() {
			activeStore.value?.scanMore()
		}

		// When the conversation changes, reset all tab stores and reload overview.
		// Do NOT force activeTab back to 'overview' here — onMounted handles the
		// initial tab, and the user's manual tab switches should be respected.
		watch(selectedToken, async (token, oldToken) => {
			if (!token) return

			// Reset every tab store so they re-fetch for the new conversation
			for (const store of Object.values(tabStores)) {
				store.reset()
			}

			// Only jump to overview when switching conversations (not on initial load)
			if (oldToken) {
				activeTab.value = 'overview'
				highlightId.value = null
			}

			overviewData.value = {}
			overviewError.value = null
			overviewLoading.value = true
			try {
				const result = await fetchShareOverview(token)
				overviewData.value = result
			} catch (err) {
				if (process.env.NODE_ENV !== 'production') {
					// eslint-disable-next-line no-console
					console.warn('[talk_browser] fetchShareOverview error:', err)
				}
				overviewError.value = err?.response?.data?.ocs?.meta?.message
					?? err?.message
					?? 'Failed to load overview'
				overviewData.value = {}
			} finally {
				overviewLoading.value = false
			}

			// If we're on a non-overview tab (restored from URL), trigger its load now
			// that the token is confirmed.
			if (activeTab.value !== 'overview') {
				tabStores[activeTab.value]?.load()
			}
		})

		// Navigate from overview to a specific item in its tab
		function goToItem({ tab, id }) {
			highlightId.value = id
			activeTab.value = tab
		}

		// ── Sync state → URL path ────────────────────────────────────────────
		watch([selectedToken, activeTab], ([token, tab]) => {
			updatePath(token, tab, !isRestoringFromPopstate)
		})

		// ── On mount: restore state from URL path ────────────────────────────
		onMounted(async () => {
			const { token: hashToken, tab: hashTab } = parsePath()

			// Restore tab BEFORE loading conversations so the selectedToken watcher
			// sees the correct activeTab and can trigger the right store.load().
			if (hashToken && hashTab && hashTab !== 'overview') {
				activeTab.value = hashTab
			}

			// Stamp the initial history entry with state so popstate can read it,
			// using replaceState so we don't push an extra entry on first load.
			updatePath(hashToken, activeTab.value, false)

			await loadConversations(hashToken)

			window.addEventListener('popstate', handlePopstate)
		})

		// ── Handle browser Back/Forward ───────────────────────────────────────
		function handlePopstate(event) {
			const state = event.state ?? parsePath()
			const token = state.token ?? null
			const tab = VALID_TABS.includes(state.tab) ? state.tab : 'overview'

			// Set flag so the watcher uses replaceState, not pushState
			isRestoringFromPopstate = true
			selectedToken.value = token
			activeTab.value = tab
			isRestoringFromPopstate = false
		}

		onUnmounted(() => {
			window.removeEventListener('popstate', handlePopstate)
		})

		return {
			// conversations
			conversations,
			filteredConversations,
			conversationsLoading,
			conversationsError,
			selectedToken,
			selectedConversation,
			loadConversations,
			// settings
			hideGroupConversations,
			showSettingsModal,
			onUpdateHideGroupConversations,
			// tabs
			activeTab,
			highlightId,
			goToItem,
			// overview
			overviewData,
			overviewLoading,
			overviewError,
			// items
			items,
			itemsLoading,
			itemsLoadingMore,
			itemsError,
			itemsHasMore,
			loadItems,
			loadMoreItems,
			scanMoreItems,
		}
	},

	methods: {
		t,
	},
}
</script>

<style>
.content.app-talk_browser {
	margin-top: 0 !important;
}
</style>

<style scoped>
.app__loading {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 16px;
	height: 100%;
	color: var(--color-text-maxcontrast);
}

.app__header {
	/* Leave room for the sidebar toggle button (~44 px) on narrow viewports */
	line-height: 51px;
	padding-inline-start: calc(44px + 16px);
}

.app__title {
	font-size: 20px;
	font-weight: 600;
	margin: 0 0 4px;
}

/* Shared highlight animation used by all list components */
@keyframes tb-highlight-fade {
	0%   { outline-color: var(--color-primary-element); }
	100% { outline-color: transparent; }
}

.app__settings-trigger {
	padding: 4px 8px;
	border-top: 1px solid var(--color-border);
}
</style>
