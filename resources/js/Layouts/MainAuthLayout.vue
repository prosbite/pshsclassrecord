<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  HomeIcon,
  UsersIcon,
  FolderIcon,
  ChartBarIcon,
  ArrowRightOnRectangleIcon,
  Bars3Icon
} from '@heroicons/vue/24/outline'

const page = usePage()
const user = page.props.auth.user
const normalizePath = (link) => {
  if (!link) {
    return ''
  }
  try {
    return new URL(link, window.location.origin).pathname
  } catch {
    return link
  }
}
const currentPath = computed(() => {
  try {
    return new URL(page.url).pathname
  } catch {
    return page.url
  }
})

const quarterlyPaths = [
  normalizePath(route('quarterly-assessments.index')),
  normalizePath(route('quarterly-assessments.upload')),
]

const isQuarterlyActive = computed(() =>
  quarterlyPaths.some((path) => currentPath.value.startsWith(path))
)

const studentsPath = normalizePath(route('students'))
const assessmentsPath = normalizePath(route('assessments.index'))
const isStudentsActive = computed(() => currentPath.value.startsWith(studentsPath))
const isAssessmentsActive = computed(() => currentPath.value.startsWith(assessmentsPath))

const sidebarOpen = ref(false)
const isCollapsed = ref(false)

// Close sidebar when clicking outside on mobile
const handleClickOutside = (e) => {
  if (sidebarOpen.value && !e.target.closest('#sidebar')) {
    sidebarOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})

// Toggle collapse (desktop)
const toggleCollapse = () => {
  isCollapsed.value = !isCollapsed.value
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900 flex">
    <!-- Sidebar -->
    <div
      id="sidebar"
      :class="[
        'fixed inset-y-0 left-0 z-50 bg-white border-r border-slate-200 transition-all duration-300 flex flex-col shadow-xl',
        sidebarOpen ? 'w-72' : 'w-0 -translate-x-full md:translate-x-0',
        isCollapsed && !sidebarOpen ? 'md:w-20' : 'md:w-72'
      ]"
    >
      <!-- Sidebar Header -->
      <div class="h-16 border-b border-slate-200 flex items-center px-6">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10">
            <svg viewBox="0 0 64 64" class="h-full w-full">
              <defs>
                <linearGradient id="pshsGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                  <stop offset="0%" stop-color="#0f172a" />
                  <stop offset="50%" stop-color="#6366f1" />
                  <stop offset="100%" stop-color="#2563eb" />
                </linearGradient>
              </defs>
              <circle cx="32" cy="32" r="30" fill="url(#pshsGradient)" />
              <path
                d="M21 40 L32 18 L43 40 Z"
                fill="none"
                stroke="#fff"
                stroke-width="4"
                stroke-linejoin="round"
              />
              <path
                d="M24 38 L32 26 L40 38"
                stroke="#fff"
                stroke-width="3"
                fill="none"
                stroke-linecap="round"
              />
              <circle cx="32" cy="32" r="4" fill="#fff" />
            </svg>
          </div>
          <div v-if="!isCollapsed || sidebarOpen" class="font-semibold text-xl tracking-tight text-slate-700">
            PSHS Class Record
          </div>
        </div>

        <!-- Collapse button (desktop only) -->
          <button
            @click="toggleCollapse"
            class="hidden md:flex ml-auto text-slate-500 hover:text-slate-700 transition-colors"
          >
            <component
              :is="isCollapsed ? 'ArrowRightOnRectangleIcon' : 'ArrowRightOnRectangleIcon'"
              class="w-5 h-5 transition-transform"
              :class="{ 'rotate-180': isCollapsed }"
            />
          </button>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 overflow-y-auto py-6 px-3 space-y-1">

        <Link
          :href="studentsPath"
          class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-slate-100 transition-all group"
          :class="{ 'bg-slate-100 text-slate-900 shadow': isStudentsActive }"
        >
          <UsersIcon class="w-5 h-5 text-slate-400 group-hover:text-slate-700 transition-colors" />
          <span v-if="!isCollapsed || sidebarOpen" class="text-sm font-medium">Students</span>
        </Link>

        <Link
          :href="assessmentsPath"
          class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-slate-100 transition-all group"
          :class="{ 'bg-slate-100 text-slate-900 shadow': isAssessmentsActive }"
        >
          <ChartBarIcon class="w-5 h-5 text-slate-400 group-hover:text-slate-700 transition-colors" />
          <span v-if="!isCollapsed || sidebarOpen" class="text-sm font-medium">Assessments</span>
        </Link>

        <Link
          :href="route('quarterly-assessments.index')"
          class="flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-slate-100 transition-all group"
          :class="{ 'bg-slate-100 text-slate-900 shadow': isQuarterlyActive }"
        >
          <FolderIcon class="w-5 h-5 text-slate-400 group-hover:text-slate-700 transition-colors" />
          <span v-if="!isCollapsed || sidebarOpen" class="text-sm font-medium">Quarterly CSV</span>
        </Link>
      </nav>

      <!-- Sidebar Footer -->
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-h-screen md:ml-0 transition-all bg-slate-50"
         :class="{ 'md:ml-20': isCollapsed && !sidebarOpen, 'md:ml-72': !isCollapsed }">

      <!-- Top Navbar -->
      <header class="h-16 bg-white border-b border-slate-200 px-6 flex items-center justify-between sticky top-0 z-40 shadow-sm">
        <div class="flex items-center gap-4">
          <!-- Mobile Burger -->
          <button
            @click="sidebarOpen = !sidebarOpen"
            class="md:hidden p-2 rounded-xl hover:bg-slate-100 text-slate-500 hover:text-slate-700"
          >
            <Bars3Icon class="w-6 h-6" />
          </button>

          <!-- Desktop Collapse Button -->
          <button
            @click="toggleCollapse"
            class="hidden md:block p-2 rounded-xl hover:bg-slate-100 text-slate-500 hover:text-slate-700"
          >
            <Bars3Icon class="w-6 h-6 transition-transform" :class="{ 'rotate-180': isCollapsed }" />
          </button>
        </div>

        <div class="flex items-center gap-4">
          <div class="text-sm text-slate-500">
            {{ new Date().toLocaleDateString('en-US', { weekday: 'long' }) }}
          </div>
          <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
            <img
              v-if="user.avatar"
              :src="user.avatar"
              class="w-9 h-9 rounded-2xl object-cover"
              alt="Avatar"
            />
            <div
              v-else
              class="w-9 h-9 bg-slate-100 text-slate-800 rounded-2xl flex items-center justify-center text-xs font-mono"
            >
              {{ user.name?.charAt(0).toUpperCase() }}
            </div>
            <div class="flex flex-col text-left">
              <span class="text-sm font-medium text-slate-900">{{ user.name }}</span>
              <span class="text-xs text-slate-500">{{ user.email }}</span>
            </div>
            <Link
              :href="route('logout')"
              method="post"
              as="button"
              class="text-slate-500 hover:text-red-500 transition-colors p-2 rounded-xl hover:bg-slate-100"
            >
              <ArrowRightOnRectangleIcon class="w-5 h-5" />
            </Link>
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="flex-1 p-6 md:p-8 overflow-auto bg-slate-50">
        <slot />
      </main>
    </div>

    <!-- Mobile Overlay -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 bg-white/70 z-40 md:hidden backdrop-blur"
      @click="sidebarOpen = false"
    />
  </div>
</template>
