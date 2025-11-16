import axios from 'axios'
import { defineStore } from 'pinia'
import { useRoute } from 'vue-router'
import { handleError } from '@/scripts/helpers/error-handling'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useGlobalStore } from '@/scripts/admin/stores/global'
import { useCompanyStore } from '@/scripts/admin/stores/company'
import appointmentStub from '@/scripts/admin/stub/appointment.js'

export const useAppointmentStore = (useWindow = false) => {
  const defineStoreFunc = useWindow ? window.pinia.defineStore : defineStore
  const { global } = window.i18n

  return defineStoreFunc({
    id: 'appointment',
    
    state: () => ({
      appointments: [],
      totalAppointments: 0,
      selectAllField: false,
      selectedAppointments: [],
      selectedViewAppointment: {},
      isFetchingInitialSettings: false,
      isFetchingViewData: false,
      dashboardStats: {
        today: 0,
        this_week: 0,
        upcoming: 0,
        completed_this_month: 0,
      },
      availableSlots: [],
      currentAppointment: {
        ...appointmentStub(),
      },
    }),

    getters: {
      isEdit: (state) => (state.currentAppointment.id ? true : false),
    },

    actions: {
      resetCurrentAppointment() {
        this.currentAppointment = {
          ...appointmentStub(),
        }
      },

      fetchAppointmentInitialSettings(isEdit) {
        const route = useRoute()
        const globalStore = useGlobalStore()

        this.isFetchingInitialSettings = true
        let editActions = []
        
        if (isEdit) {
          editActions = [this.fetchAppointment(route.params.id)]
        }

        Promise.all([...editActions])
          .then(async ([res1]) => {
            this.isFetchingInitialSettings = false
          })
          .catch((error) => {
            handleError(error)
          })
      },

      fetchAppointments(params) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/appointments`, { params })
            .then((response) => {
              this.appointments = response.data.data
              this.totalAppointments = response.data.meta?.total || response.data.data.length
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchAppointment(id) {
        return new Promise((resolve, reject) => {
          axios
            .get(`/api/v1/appointments/${id}`)
            .then((response) => {
              Object.assign(this.currentAppointment, response.data.data)
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchViewAppointment(id) {
        return new Promise((resolve, reject) => {
          this.isFetchingViewData = true
          axios
            .get(`/api/v1/appointments/${id}`)
            .then((response) => {
              this.selectedViewAppointment = {}
              Object.assign(this.selectedViewAppointment, response.data.data)
              this.isFetchingViewData = false
              resolve(response)
            })
            .catch((err) => {
              this.isFetchingViewData = false
              handleError(err)
              reject(err)
            })
        })
      },

      addAppointment(data) {
        return new Promise((resolve, reject) => {
          axios
            .post('/api/v1/appointments', data)
            .then((response) => {
              this.appointments.unshift(response.data.data)

              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: global.t('appointments.created_message'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateAppointment(data) {
        return new Promise((resolve, reject) => {
          axios
            .put(`/api/v1/appointments/${data.id}`, data)
            .then((response) => {
              if (response.data) {
                let pos = this.appointments.findIndex(
                  (appointment) => appointment.id === response.data.data.id
                )
                if (pos !== -1) {
                  this.appointments[pos] = response.data.data
                }
                const notificationStore = useNotificationStore()
                notificationStore.showNotification({
                  type: 'success',
                  message: global.t('appointments.updated_message'),
                })
              }
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      updateAppointmentStatus(id, status, reason = null) {
        return new Promise((resolve, reject) => {
          axios
            .patch(`/api/v1/appointments/${id}/status`, { status, reason })
            .then((response) => {
              let pos = this.appointments.findIndex(
                (appointment) => appointment.id === id
              )
              if (pos !== -1) {
                this.appointments[pos] = response.data.data
              }
              
              const notificationStore = useNotificationStore()
              notificationStore.showNotification({
                type: 'success',
                message: global.t('appointments.status_updated_message'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      deleteAppointment(id) {
        const notificationStore = useNotificationStore()
        return new Promise((resolve, reject) => {
          axios
            .delete(`/api/v1/appointments/${id}`)
            .then((response) => {
              let index = this.appointments.findIndex(
                (appointment) => appointment.id === id
              )
              this.appointments.splice(index, 1)
              notificationStore.showNotification({
                type: 'success',
                message: global.t('appointments.deleted_message'),
              })
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchDashboardStats() {
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/appointments/dashboard-stats')
            .then((response) => {
              this.dashboardStats = response.data
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      fetchAvailableSlots(date, excludeAppointmentId = null) {
        const companyStore = useCompanyStore()
        return new Promise((resolve, reject) => {
          axios
            .get('/api/v1/appointments/available-slots', {
              params: {
                date: date,
                company_id: companyStore.selectedCompany?.id,
                exclude_appointment_id: excludeAppointmentId,
              },
            })
            .then((response) => {
              this.availableSlots = response.data.slots
              resolve(response)
            })
            .catch((err) => {
              handleError(err)
              reject(err)
            })
        })
      },

      selectAppointment(data) {
        this.selectedAppointments = data
        if (this.selectedAppointments.length === this.appointments.length) {
          this.selectAllField = true
        } else {
          this.selectAllField = false
        }
      },

      selectAllAppointments() {
        if (this.selectedAppointments.length === this.appointments.length) {
          this.selectedAppointments = []
          this.selectAllField = false
        } else {
          let allAppointmentIds = this.appointments.map(
            (appointment) => appointment.id
          )
          this.selectedAppointments = allAppointmentIds
          this.selectAllField = true
        }
      },
    },
  })()
}
