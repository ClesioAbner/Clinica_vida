/**
 * Páginas legais: o índice acende a secção que está a ser lida.
 */
(function () {
	'use strict';

	var ligacoes = document.querySelectorAll('.lg-indice a[href^="#"]');
	if (!ligacoes.length || !('IntersectionObserver' in window)) {
		return;
	}

	function acender(id) {
		ligacoes.forEach(function (a) {
			a.setAttribute('aria-current', a.getAttribute('href') === '#' + id ? 'true' : 'false');
		});
	}

	var observador = new IntersectionObserver(
		function (entradas) {
			entradas.forEach(function (e) {
				if (e.isIntersecting) {
					acender(e.target.id);
				}
			});
		},
		{ rootMargin: '-20% 0px -70% 0px' }
	);

	ligacoes.forEach(function (a) {
		var alvo = document.getElementById(a.getAttribute('href').slice(1));
		if (alvo) {
			observador.observe(alvo);
		}
	});
})();
