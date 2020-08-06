import Vue from 'vue'
import { getRequestToken } from '@nextcloud/auth'
import App from './App'

// eslint-disable-next-line camelcase
__webpack_nonce__ = btoa(getRequestToken())

// Correct the root of the app for chunk loading
// OC.linkTo matches the apps folders
// OC.generateUrl ensure the index.php (or not)
// eslint-disable-next-line
__webpack_public_path__ = OC.linkTo('weather_status', 'js/')

Vue.prototype.t = t
Vue.prototype.$t = t

document.addEventListener('DOMContentLoaded', function() {
	if (!OCA.Dashboard) {
		return
	}

	OCA.Dashboard.registerStatus('weather', (el) => {
		const Dashboard = Vue.extend(App)
		return new Dashboard({
			propsData: {
				inline: true,
			},
		}).$mount(el)
	})
})
