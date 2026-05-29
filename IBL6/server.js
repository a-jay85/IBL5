// Custom pm2 entry point for IBL6.
//
// It boots the unchanged @sveltejs/adapter-node server (which keeps all of the
// adapter's origin/CSRF/body-size handling) and then emits a pm2 readiness
// signal once the HTTP server is actually listening. Paired with
// `wait_ready: true` in ecosystem.config.cjs, this makes `pm2 start` block
// until IBL6 is genuinely serving requests, eliminating the deploy smoke-test
// startup race (the server used to be curled within ~0.6s of fork).
//
// adapter-node's build/index.js calls server.listen() unconditionally at import
// time and exports the polka instance, whose underlying http.Server is exposed
// as `server.server`.
import { server } from './build/index.js';

function signalReady() {
	if (typeof process.send === 'function') {
		process.send('ready');
	}
}

const httpServer = server.server;

if (httpServer && httpServer.listening) {
	// listen() already fired before this module finished evaluating.
	signalReady();
} else if (httpServer) {
	httpServer.once('listening', signalReady);
} else {
	// Defensive fallback: if the adapter ever stops exposing the http server,
	// signal on the next tick so pm2 doesn't block until listen_timeout. The
	// deploy smoke test still gates real reachability over the proxy.
	process.nextTick(signalReady);
}
