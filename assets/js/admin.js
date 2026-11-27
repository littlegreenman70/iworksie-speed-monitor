/**
 * iWorks.ie Speed Monitor admin: runs manual tests one request at a time.
 */
(function () {
	'use strict';

	var cfg = window.iwiesmAdmin;
	if (!cfg) {
		return;
	}

	function format(str, args) {
		var i = 0;
		return String(str).replace(/%(\d+)\$[sd]|%[sd]/g, function (m, n) {
			var idx = n ? parseInt(n, 10) - 1 : i++;
			return typeof args[idx] === 'undefined' ? m : String(args[idx]);
		});
	}

	function pathOf(url) {
		try {
			var u = new window.URL(url);
			return u.pathname + u.search;
		} catch (e) {
			return url;
		}
	}

	function setProgress(pct, message) {
		if (window.iwtCoreUi && typeof window.iwtCoreUi.setProgress === 'function') {
			window.iwtCoreUi.setProgress('iwiesm-progress', pct, message);
			return;
		}
		var el = document.getElementById('iwiesm-progress');
		if (!el) {
			return;
		}
		var msg = el.querySelector('.iwt-progress__message');
		if (msg) {
			msg.textContent = message;
		}
	}

	function addError(text) {
		var box = document.querySelector('[data-iwiesm-errors]');
		if (!box) {
			return;
		}
		var div = document.createElement('div');
		div.className = 'notice notice-warning inline';
		var p = document.createElement('p');
		p.textContent = text;
		div.appendChild(p);
		box.appendChild(div);
	}

	function runOne(test) {
		var body = new window.FormData();
		body.append('action', 'iwiesm_run_test');
		body.append('nonce', cfg.nonce);
		body.append('url', test.url);
		body.append('strategy', test.strategy);
		return window.fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (res) {
			return res.json().catch(function () {
				return { success: false, data: { message: cfg.strings.requestError } };
			});
		}).catch(function () {
			return { success: false, data: { message: cfg.strings.requestError } };
		});
	}

	function runAll(buttons) {
		var tests = cfg.tests || [];
		var total = tests.length;
		var failed = 0;
		var index = 0;

		buttons.forEach(function (b) {
			b.disabled = true;
			b.setAttribute('aria-busy', 'true');
		});
		var progress = document.getElementById('iwiesm-progress');
		if (progress) {
			progress.classList.remove('is-idle');
			progress.hidden = false;
		}
		var box = document.querySelector('[data-iwiesm-errors]');
		if (box) {
			box.textContent = '';
		}

		function next() {
			if (index >= total) {
				setProgress(100, failed ? format(cfg.strings.doneErrors, [failed]) : cfg.strings.done);
				window.setTimeout(function () {
					window.location.reload();
				}, failed ? 4000 : 800);
				return;
			}
			var test = tests[index];
			var device = test.strategy === 'desktop' ? cfg.strings.desktop : cfg.strings.mobile;
			setProgress(Math.round((index / total) * 100), format(cfg.strings.testing, [index + 1, total, pathOf(test.url), device]));
			runOne(test).then(function (res) {
				if (!res || !res.success) {
					failed++;
					var msg = res && res.data && res.data.message ? res.data.message : cfg.strings.requestError;
					addError(pathOf(test.url) + ' (' + device + '): ' + cfg.strings.failed + ' ' + msg);
				}
				index++;
				next();
			});
		}
		next();
	}

	document.addEventListener('DOMContentLoaded', function () {
		var buttons = Array.prototype.slice.call(document.querySelectorAll('[data-iwiesm-run]'));
		buttons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				runAll(buttons);
			});
		});

		var progress = document.getElementById('iwiesm-progress');
		if (progress) {
			progress.hidden = true;
		}

		Array.prototype.slice.call(document.querySelectorAll('form[data-iwiesm-confirm]')).forEach(function (form) {
			form.addEventListener('submit', function (e) {
				// eslint-disable-next-line no-alert
				if (!window.confirm(cfg.strings.confirmClear)) {
					e.preventDefault();
				}
			});
		});
	});
})();
