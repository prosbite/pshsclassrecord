<script setup>
import { computed, ref, watch, onMounted, onUnmounted, onBeforeUnmount } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  HomeIcon,
  UsersIcon,
  ChartBarIcon,
  AcademicCapIcon,
  ClockIcon,
  BookOpenIcon,
  ClipboardDocumentListIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon,
  Cog6ToothIcon,
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

const studentsPath = normalizePath(route('students'))
const dashboardPath = normalizePath(route('dashboard'))
const assessmentsPath = normalizePath(route('assessments.index'))
const exercisesPath = normalizePath(route('exercises.index'))
const trackerPath = normalizePath(route('tracker.index'))
const errorLogsPath = normalizePath(route('error-logs.index'))
const topicsPath = normalizePath(route('topics.index'))
const questionnairesPath = normalizePath(route('questionnaires.index'))
const questionsPath = normalizePath(route('questions.index'))
const settingsPath = normalizePath(route('settings.edit'))
const isDashboardActive = computed(() => currentPath.value === dashboardPath)
const isStudentsActive = computed(() => currentPath.value.startsWith(studentsPath))
const isAssessmentsActive = computed(() => currentPath.value.startsWith(assessmentsPath))
const isExercisesActive = computed(() => currentPath.value.startsWith(exercisesPath))
const isTrackerActive = computed(() => currentPath.value.startsWith(trackerPath))
const isErrorLogsActive = computed(() => currentPath.value.startsWith(errorLogsPath))
const isTopicsActive = computed(() => currentPath.value.startsWith(topicsPath))
const isQuestionnairesActive = computed(() => currentPath.value.startsWith(questionnairesPath))
const isQuestionsActive = computed(() => currentPath.value.startsWith(questionsPath))
const isSettingsActive = computed(() => currentPath.value.startsWith(settingsPath))

const navItems = computed(() => [
  { label: 'Dashboard', href: dashboardPath, active: isDashboardActive.value, icon: HomeIcon },
  { label: 'Students', href: studentsPath, active: isStudentsActive.value, icon: UsersIcon },
  { label: 'Assessments', href: assessmentsPath, active: isAssessmentsActive.value, icon: ChartBarIcon },
  { label: 'Exercises', href: exercisesPath, active: isExercisesActive.value, icon: AcademicCapIcon },
  { label: 'Topics', href: topicsPath, active: isTopicsActive.value, icon: BookOpenIcon },
  { label: 'Questionnaires', href: questionnairesPath, active: isQuestionnairesActive.value, icon: ClipboardDocumentListIcon },
  { label: 'Questions', href: questionsPath, active: isQuestionsActive.value, icon: DocumentTextIcon },
  { label: 'Tracker', href: trackerPath, active: isTrackerActive.value, icon: ClockIcon },
  { label: 'Error Logs', href: errorLogsPath, active: isErrorLogsActive.value, icon: ExclamationTriangleIcon },
  { label: 'Settings', href: settingsPath, active: isSettingsActive.value, icon: Cog6ToothIcon },
])

const flashSuccess = computed(() => page.props.flash?.success ?? null)
const flashError = computed(() => page.props.flash?.error ?? null)

const toast = ref(null)
let toastTimer = null

const showToast = (message, type) => {
  toast.value = { message, type }

  if (toastTimer) {
    clearTimeout(toastTimer)
  }

  toastTimer = setTimeout(() => {
    toast.value = null
  }, 4000)
}

watch(
  [flashSuccess, flashError],
  ([success, error]) => {
    if (success) {
      showToast(success, 'success')
    } else if (error) {
      showToast(error, 'error')
    }
  },
  { immediate: true },
)

const sidebarOpen = ref(false)
const isCollapsed = ref(true)

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

onBeforeUnmount(() => {
  if (toastTimer) {
    clearTimeout(toastTimer)
  }
})

// Toggle collapse (desktop)
const toggleCollapse = () => {
  isCollapsed.value = !isCollapsed.value
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900 flex">
    <!-- Toast -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="toast"
        class="fixed right-6 top-6 z-[60] flex max-w-sm items-start gap-3 rounded-2xl border bg-white px-5 py-4 shadow-lg"
        :class="toast.type === 'success' ? 'border-emerald-200' : 'border-rose-200'"
        role="status"
      >
        <span
          class="mt-0.5 h-2.5 w-2.5 flex-none rounded-full"
          :class="toast.type === 'success' ? 'bg-emerald-500' : 'bg-rose-500'"
        ></span>
        <p class="text-sm font-medium" :class="toast.type === 'success' ? 'text-emerald-800' : 'text-rose-800'">
          {{ toast.message }}
        </p>
        <button
          type="button"
          class="ml-auto text-slate-400 transition hover:text-slate-600"
          @click="toast = null"
        >
          ×
        </button>
      </div>
    </Transition>

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
            SPMSS
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
      <nav
        class="flex-1 py-6 px-3 space-y-1"
        :class="isCollapsed && !sidebarOpen ? 'overflow-visible' : 'overflow-y-auto'"
      >
        <Link
          v-for="item in navItems"
          :key="item.label"
          :href="item.href"
          class="group relative flex items-center gap-3 px-4 py-3 rounded-2xl hover:bg-slate-100 transition-all"
          :class="{ 'bg-slate-100 text-slate-900 shadow': item.active }"
        >
          <component
            :is="item.icon"
            class="w-5 h-5 flex-none text-slate-400 group-hover:text-slate-700 transition-colors"
          />
          <span v-if="!isCollapsed || sidebarOpen" class="text-sm font-medium">{{ item.label }}</span>

          <!-- Tooltip when collapsed -->
          <span
            v-if="isCollapsed && !sidebarOpen"
            class="pointer-events-none absolute left-full top-1/2 z-[60] ml-3 -translate-y-1/2 whitespace-nowrap rounded-xl bg-slate-900 px-3 py-2 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100"
          >
            {{ item.label }}
          </span>
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
