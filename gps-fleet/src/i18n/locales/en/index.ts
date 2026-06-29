import common from './common'
import customerLayers from './customerLayers'
import dashboard from './dashboard'
import history from './history'
import layout from './layout'
import login from './login'
import notifications from './notifications'
import reports from './reports'
import tracking from './tracking'
import vehicleManagement from './vehicleManagement'

export default {
    ...common,
    ...customerLayers,
    ...dashboard,
    ...layout,
    ...login,
    ...notifications,
    ...reports,
    ...tracking,
    ...history,
    ...vehicleManagement,
} as const
