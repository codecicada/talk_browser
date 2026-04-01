<template>
	<NcContent app-name="talk_browser">
		<!-- Left navigation: conversation picker -->
		<NcAppNavigation>
			<template #list>
				<ConversationPicker
					v-model="selectedToken"
					:conversations="conversations"
					:loading="conversationsLoading"
				/>
			</template>
		</NcAppNavigation>

		<!-- Main content area -->
		<NcAppContent>
			<!-- Loading conversations -->
			<div v-if="conversationsLoading" class="app__loading">
				<NcLoadingIcon :size="48" />
				<p>{{ t('talk_browser', 'Loading conversations…') }}</p>
			</div>

			<!-- Error loading conversations -->
			<NcEmptyContent
				v-else-if="conversationsError"
				:name="t('talk_browser', 'Could not load conversations')"
				:description="conversationsError"
			>
				<template #icon>
					<span class="icon-error" />
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
					<span class="icon-talk" />
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
					<template #default="{ activeTab: tab, searchQuery }">
						<!-- Overview -->
					<OverviewPanel
						v-if="tab === 'overview'"
						:overview-data="overviewData"
						:loading="overviewLoading"
						@go-to-tab="activeTab = $event"
						@go-to-item="goToItem"
					/>

						<!-- Item loading error (non-overview tabs) -->
						<NcEmptyContent
							v-else-if="itemsError && !itemsLoading"
							:name="t('talk_browser', 'Could not load content')"
							:description="itemsError"
						>
							<template #icon>
								<span class="icon-error" />
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
							:link-scan-done="linkScanDone"
							:search="searchQuery"
							:highlight-id="highlightId"
							@load-more="loadMoreItems"
						/>

						<!-- Locations / deckcard / other / recording -->
						<GenericList
							v-else
							:items="items"
							:loading="itemsLoading"
							:loading-more="itemsLoadingMore"
							:has-more="itemsHasMore"
							:search="searchQuery"
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
	NcEmptyContent,
	NcLoadingIcon,
} from '@nextcloud/vue'
import { translate as t } from '@nextcloud/l10n'
import { ref, watch, onMounted } from 'vue'
import { generateUrl } from '@nextcloud/router'

import ConversationPicker from './components/ConversationPicker.vue'
import ContentTabs from './components/ContentTabs.vue'
import OverviewPanel from './components/OverviewPanel.vue'
import MediaGallery from './components/MediaGallery.vue'
import FileList from './components/FileList.vue'
import AudioList from './components/AudioList.vue'
import LinkList from './components/LinkList.vue'
import GenericList from './components/GenericList.vue'

import { useConversations } from './composables/useConversations.js'
import { useSharedItems } from './composables/useSharedItems.js'
import { fetchShareOverview } from './api/talk.js'

export default {
	name: 'App',

	components: {
		NcAppContent,
		NcAppNavigation,
		NcButton,
		NcContent,
		NcEmptyContent,
		NcLoadingIcon,
		ConversationPicker,
		ContentTabs,
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
			const [token, tab] = rest.split('/')
			return {
				token: token || null,
				tab: VALID_TABS.includes(tab) ? tab : null,
			}
		}

		function updatePath(token, tab) {
			const path = token
				? `${BASE}/${token}/${tab ?? 'overview'}`
				: `${BASE}/`
			if (window.location.pathname !== path) {
				window.history.replaceState(null, '', path)
			}
		}

		// ── Conversations ────────────────────────────────────────────────────
		const {
			conversations,
			loading: conversationsLoading,
			error: conversationsError,
			selectedToken,
			selectedConversation,
			load: loadConversations,
		} = useConversations()

		// ── Active tab ───────────────────────────────────────────────────────
		const activeTab = ref('overview')

		// ── Highlighted item (set when navigating from overview to a tab item) ──
		const highlightId = ref(null)

		// ── Overview data (flat object keyed by type) ────────────────────────
		const overviewData = ref({})
		const overviewLoading = ref(false)

		// ── Shared items (for non-overview tabs) ─────────────────────────────
		const objectTypeRef = ref('overview')

		const {
			items,
			loading: itemsLoading,
			loadingMore: itemsLoadingMore,
			error: itemsError,
			hasMore: itemsHasMore,
			linkScanDone,
			load: loadItems,
			loadMore: loadMoreItems,
		} = useSharedItems(selectedToken, objectTypeRef)

		// When the conversation changes, reload overview and reset tab
		watch(selectedToken, async (token) => {
			if (!token) return
			activeTab.value = 'overview'
			highlightId.value = null
			overviewData.value = {}
			overviewLoading.value = true
			try {
				const result = await fetchShareOverview(token)
				overviewData.value = result
			} catch (err) {
				overviewData.value = {}
			} finally {
				overviewLoading.value = false
			}
		})

		// When the active tab changes, update the objectType ref so useSharedItems reacts.
		// Do NOT call loadItems() here — the watch inside useSharedItems fires automatically
		// when objectTypeRef changes, and calling it twice causes duplicate results (e.g. links).
		watch(activeTab, (tab) => {
			if (tab === 'overview') return
			objectTypeRef.value = tab
		})

		// Navigate from overview to a specific item in its tab
		function goToItem({ tab, id }) {
			highlightId.value = id
			activeTab.value = tab
			if (tab !== 'overview') {
				objectTypeRef.value = tab
			}
		}

		// ── Sync state → URL path ────────────────────────────────────────────
		watch([selectedToken, activeTab], ([token, tab]) => {
			updatePath(token, tab)
		})

		// ── On mount: restore state from URL path ────────────────────────────
		onMounted(async () => {
			const { token: hashToken, tab: hashTab } = parsePath()

			await loadConversations(hashToken)

			// After loadConversations, the selectedToken watcher fires and resets
			// activeTab to 'overview'. We wait for the next microtask tick so the
			// overview fetch triggered by that watcher has started, then apply the
			// hash tab on top — the overview fetch continues in the background and
			// overviewLoading guards the UI.
			if (hashToken && hashTab && hashTab !== 'overview' && selectedToken.value === hashToken) {
				// Let the watcher's async overview-fetch kick off first
				await Promise.resolve()
				activeTab.value = hashTab
				objectTypeRef.value = hashTab
			}
		})

		return {
			// conversations
			conversations,
			conversationsLoading,
			conversationsError,
			selectedToken,
			selectedConversation,
			loadConversations,
			// tabs
			activeTab,
			highlightId,
			goToItem,
			// overview
			overviewData,
			overviewLoading,
			// items
			items,
			itemsLoading,
			itemsLoadingMore,
			itemsError,
			itemsHasMore,
			linkScanDone,
			loadItems,
			loadMoreItems,
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
</style>
