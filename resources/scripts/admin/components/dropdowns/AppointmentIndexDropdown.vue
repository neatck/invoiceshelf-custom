<template>
  <BaseDropdown>
    <template #activator>
      <BaseIcon name="EllipsisHorizontalIcon" class="h-5 text-gray-500" />
    </template>

    <!-- View Appointment -->
    <router-link
      v-if="userStore.hasAbilities(abilities.VIEW_APPOINTMENT)"
      :to="`/admin/appointments/${row.id}/view`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="EyeIcon"
          class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
        />
        {{ $t('general.view') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Edit Appointment -->
    <router-link
      v-if="userStore.hasAbilities(abilities.EDIT_APPOINTMENT)"
      :to="`/admin/appointments/${row.id}/edit`"
    >
      <BaseDropdownItem>
        <BaseIcon
          name="PencilIcon"
          class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
        />
        {{ $t('general.edit') }}
      </BaseDropdownItem>
    </router-link>

    <!-- Mark as Confirmed -->
    <BaseDropdownItem
      v-if="
        row.status === 'scheduled' &&
        userStore.hasAbilities(abilities.EDIT_APPOINTMENT)
      "
      @click="updateStatus(row.id, 'confirmed')"
    >
      <BaseIcon
        name="CheckCircleIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('appointments.mark_as_confirmed') }}
    </BaseDropdownItem>

    <!-- Mark as Completed -->
    <BaseDropdownItem
      v-if="
        (row.status === 'scheduled' || row.status === 'confirmed') &&
        userStore.hasAbilities(abilities.EDIT_APPOINTMENT)
      "
      @click="updateStatus(row.id, 'completed')"
    >
      <BaseIcon
        name="CheckIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('appointments.mark_as_completed') }}
    </BaseDropdownItem>

    <!-- Cancel Appointment -->
    <BaseDropdownItem
      v-if="
        (row.status === 'scheduled' || row.status === 'confirmed') &&
        userStore.hasAbilities(abilities.EDIT_APPOINTMENT)
      "
      @click="cancelAppointment(row.id)"
    >
      <BaseIcon
        name="XCircleIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('appointments.cancel') }}
    </BaseDropdownItem>

    <!-- Delete Appointment -->
    <BaseDropdownItem
      v-if="userStore.hasAbilities(abilities.DELETE_APPOINTMENT)"
      @click="removeAppointment(row.id)"
    >
      <BaseIcon
        name="TrashIcon"
        class="w-5 h-5 mr-3 text-gray-400 group-hover:text-gray-500"
      />
      {{ $t('general.delete') }}
    </BaseDropdownItem>
  </BaseDropdown>
</template>

<script setup>
import { useAppointmentStore } from '@/scripts/admin/stores/appointment'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/scripts/admin/stores/user'
import abilities from '@/scripts/admin/stub/abilities'

const props = defineProps({
  row: {
    type: Object,
    default: null,
  },
  table: {
    type: Object,
    default: null,
  },
  loadData: {
    type: Function,
    default: () => {},
  },
})

const appointmentStore = useAppointmentStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()
const { t } = useI18n()

async function updateStatus(id, status) {
  await appointmentStore.updateAppointmentStatus(id, status)
  props.loadData()
}

function cancelAppointment(id) {
  dialogStore
    .openDialog({
      title: t('appointments.cancel_appointment'),
      message: t('appointments.confirm_cancel'),
      yesLabel: t('general.yes'),
      noLabel: t('general.no'),
    })
    .then(async (response) => {
      if (response) {
        await appointmentStore.updateAppointmentStatus(id, 'cancelled')
        props.loadData()
      }
    })
}

function removeAppointment(id) {
  dialogStore
    .openDialog({
      title: t('general.are_you_sure'),
      message: t('appointments.confirm_delete'),
      yesLabel: t('general.ok'),
      noLabel: t('general.cancel'),
    })
    .then(async (response) => {
      if (response) {
        await appointmentStore.deleteAppointment(id)
        props.loadData()
      }
    })
}
</script>
