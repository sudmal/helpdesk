<template>
  <component :is="iconComponent" v-bind="$attrs" />
</template>

<script setup>
import { computed } from 'vue'
import {
  LayoutGrid, Ticket, Calendar, MapPin, Users, Database,
  Settings, LogOut, Menu, CheckCircle, AlertTriangle,
  Clock, XCircle, Plus, Search, Filter, ChevronDown,
  ChevronLeft, ChevronRight, Edit2, Trash2, Download,
  Paperclip, MessageSquare, Play, Pause, X, Check,
  RefreshCw, ArrowLeft, Eye, MoreVertical, Phone,
  FileText, Image, Video, Music, File,
} from 'lucide-vue-next'

const props = defineProps({ name: { type: String, required: true } })

const MAP = {
  'grid': LayoutGrid, 'ticket': Ticket, 'calendar': Calendar,
  'map-pin': MapPin, 'users': Users, 'database': Database,
  'settings': Settings, 'log-out': LogOut, 'menu': Menu,
  'check-circle': CheckCircle, 'alert-triangle': AlertTriangle,
  'clock': Clock, 'x-circle': XCircle, 'plus': Plus,
  'search': Search, 'filter': Filter, 'chevron-down': ChevronDown,
  'chevron-left': ChevronLeft, 'chevron-right': ChevronRight,
  'edit': Edit2, 'trash': Trash2, 'download': Download,
  'paperclip': Paperclip, 'message': MessageSquare,
  'play': Play, 'pause': Pause, 'x': X, 'check': Check,
  'refresh': RefreshCw, 'arrow-left': ArrowLeft, 'eye': Eye,
  'more': MoreVertical, 'phone': Phone, 'file-text': FileText,
  'image': Image, 'video': Video, 'music': Music, 'file': File,
}

const iconComponent = computed(() => MAP[props.name] ?? File)
</script>
