<template>
  <div class="flex items-center gap-2">
    <button @click="toggle"
            :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-medium transition-colors',
                     subscribed
                       ? 'bg-green-100 text-green-700 hover:bg-green-200'
                       : 'bg-gray-100 text-gray-600 hover:bg-gray-200']"
            :disabled="loading">
      <span>{{ subscribed ? '🔔' : '🔕' }}</span>
      <span>{{ loading ? '...' : (subscribed ? 'Уведомления вкл.' : 'Включить уведомления') }}</span>
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const subscribed = ref(false)
const loading    = ref(false)
let swReg = null

async function getVapidKey() {
  const { data } = await axios.get('/push/vapid-key')
  return data.key
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4)
  const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw     = window.atob(base64)
  return Uint8Array.from([...raw].map(c => c.charCodeAt(0)))
}

onMounted(async () => {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) return

  swReg = await navigator.serviceWorker.ready
  const sub = await swReg.pushManager.getSubscription()
  subscribed.value = !!sub
})

async function toggle() {
  if (!swReg) return
  loading.value = true

  try {
    if (subscribed.value) {
      // Отписываемся
      const sub = await swReg.pushManager.getSubscription()
      if (sub) {
        await axios.post('/push/unsubscribe', { endpoint: sub.endpoint })
        await sub.unsubscribe()
      }
      subscribed.value = false
    } else {
      // Запрашиваем разрешение
      const permission = await Notification.requestPermission()
      if (permission !== 'granted') {
        alert('Разрешите уведомления в браузере')
        return
      }

      // Подписываемся
      const vapidKey = await getVapidKey()
      const sub = await swReg.pushManager.subscribe({
        userVisibleOnly:      true,
        applicationServerKey: urlBase64ToUint8Array(vapidKey),
      })

      const subJson = sub.toJSON()
      await axios.post('/push/subscribe', {
        endpoint: subJson.endpoint,
        keys:     subJson.keys,
      })

      subscribed.value = true
    }
  } catch (e) {
    console.error('Push error:', e)
    alert('Ошибка: ' + e.message)
  } finally {
    loading.value = false
  }
}
</script>
