<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

const page = usePage();
const user = computed(() => page.props.auth.user ?? {});
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <!-- Subtle top accent -->
    <div class="h-1 bg-gradient-to-r from-indigo-500 via-violet-500 to-fuchsia-500"></div>

    <!-- Header -->
    <header class="sticky top-0 z-50 border-b border-slate-200 bg-white/95 backdrop-blur-md shadow-sm">
      <div class="mx-auto max-w-6xl px-6 py-5">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <!-- Left: Branding & Welcome -->
          <div class="flex flex-col gap-1">
            <div class="flex items-center gap-3">
              <div class="h-7 w-7 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 flex items-center justify-center">
                <span class="text-white text-[15px] font-semibold tracking-tighter">S</span>
              </div>
              <p class="text-xs font-medium uppercase tracking-[0.125em] text-slate-400">Student Portal</p>
            </div>

            <div>
              <h1 class="text-xl font-semibold tracking-tight text-slate-900">
                Welcome back, {{ user.name ?? 'Scholar' }}
              </h1>
              <p class="mt-1 text-sm text-slate-500">
                Track your progress and stay connected with your teacher
              </p>
            </div>
          </div>

          <!-- Right: User Dropdown -->
          <Dropdown align="right" width="48">
            <template #trigger>
              <button
                type="button"
                class="group inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-6 py-2.5 text-sm font-medium text-slate-700 transition-all hover:border-slate-300 hover:bg-slate-50 hover:shadow-sm active:scale-[0.985]"
              >
                <div class="flex items-center gap-2.5">
                  <!-- Optional avatar circle -->
                  <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-medium text-sm ring-2 ring-white">
                    {{ user.name ? user.name.charAt(0).toUpperCase() : 'S' }}
                  </div>
                  <span class="font-semibold">{{ user.name ?? 'Scholar' }}</span>
                </div>

                <svg
                  class="h-4 w-4 text-slate-400 transition-transform group-hover:rotate-180"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path
                    fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 10.943l3.707-3.707a.75.75 0 111.06 1.06l-4.237 4.237a.75.75 0 01-1.06 0L5.23 8.27a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd"
                  />
                </svg>
              </button>
            </template>

            <template #content>
              <div class="py-1">
                <DropdownLink :href="route('password.change')">
                  Change Password
                </DropdownLink>
                <DropdownLink :href="route('logout')" method="post" as="button">
                  Log Out
                </DropdownLink>
              </div>
            </template>
          </Dropdown>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="mx-auto max-w-6xl px-6 py-10 lg:py-14">
      <slot />
    </main>
  </div>
</template>
