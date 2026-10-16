/**
 * Aviso de cookies: mostra o cartão a quem ainda não escolheu (depois do ecrã
 * de carregamento) e guarda a escolha num cookie de 12 meses. O botão
 * «Preferências de cookies» do rodapé volta a abri-lo.
 */
(function () {
	'use strict';

	var aviso = document.querySelector('[data-cookies]');
	if (!aviso) {
		return;
	}

	var NOME = 'clinica_cookies';
	var DOZE_MESES = 60 * 60 * 24 * 365;
	var origem = null;

	function mostrar() {
		aviso.hidden = false;
		requestAnimationFrame(function () {
			aviso.classList.add('is-visivel');
		});
	}

	function esconder() {
		aviso.classList.remove('is-visivel');
		aviso.hidden = true;
		if (origem) {
			origem.focus({ preventScroll: true });
			origem = null;
		}
	}

	function escolher(valor) {
		var seguro = 'https:' === location.protocol ? '; Secure' : '';
		document.cookie =
			NOME + '=' + valor + '; Max-Age=' + DOZE_MESES + '; Path=/; SameSite=Lax' + seguro;
		esconder();
	}

	aviso.querySelectorAll('[data-cookies-escolha]').forEach(function (botao) {
		botao.addEventListener('click', function () {
			escolher(botao.getAttribute('data-cookies-escolha'));
		});
	});

	document.querySelectorAll('[data-cookies-abrir]').forEach(function (botao) {
		botao.addEventListener('click', function () {
			origem = botao;
			mostrar();
			aviso.querySelector('[data-cookies-escolha]').focus({ preventScroll: true });
		});
	});

	document.addEventListener('keydown', function (ev) {
		if ('Escape' === ev.key && !aviso.hidden && origem) {
			esconder();
		}
	});

	if (aviso.hasAttribute('data-pendente')) {
		(window.clinicaPronto || Promise.resolve()).then(function () {
			setTimeout(mostrar, 700);
		});
	}
})();
