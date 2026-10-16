/**
 * Finanças da gestão (forma "FinEdge"): animações com Motion — o Framer
 * Motion em JS puro, alojado no tema (assets/js/vendor/motion-11.18.2.js) —
 * e as interações da vista: pesquisa nos pagamentos, "Ver todos", carrossel
 * das contas e o cartão que inclina com o rato.
 *
 * A vista começa escondida: as entradas correm quando a gestão a mostra
 * (evento "clinica:vista"). Com movimento reduzido ou sem Motion, tudo fica
 * no estado final e as interações continuam a funcionar.
 */
(function () {
	'use strict';

	var vista = document.querySelector('.fx');
	if (!vista) {
		return;
	}
	var M = window.Motion;
	var mover =
		!!M &&
		!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	var SUAVE = [0.22, 1, 0.36, 1];
	var MOLA = { type: 'spring', stiffness: 160, damping: 18 };
	var feito = false;

	function lista(sel, raiz) {
		return Array.prototype.slice.call((raiz || vista).querySelectorAll(sel));
	}
	function milhares(n) {
		return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
	}

	/* Entrada: cartões em cascata, números a contar, gráficos a crescer. */
	function entrar() {
		if (feito || !mover) {
			return;
		}
		feito = true;
		lista('[data-fx-grupo]').forEach(function (g, i) {
			M.animate(
				g.children,
				{ opacity: [0, 1], y: [18, 0] },
				{ duration: 0.6, ease: SUAVE, delay: M.stagger(0.07, { startDelay: i * 0.12 }) }
			);
		});
		lista('[data-fx-n]').forEach(function (el) {
			var alvo = parseFloat(el.getAttribute('data-fx-n')) || 0,
				suf = el.getAttribute('data-fx-suf') || '';
			M.animate(0, alvo, {
				duration: 1.2,
				ease: SUAVE,
				onUpdate: function (v) {
					el.textContent = milhares(v) + suf;
				},
			});
		});
		var barras = lista('.gf-col__barra, .gf-col__pilha, .gf-div__cima i, .gf-div__baixo i');
		if (barras.length) {
			M.animate(
				barras,
				{ scaleY: [0, 1] },
				{ duration: 0.7, ease: SUAVE, delay: M.stagger(0.02, { startDelay: 0.25 }) }
			);
		}
		lista('.gf-svg').forEach(function (svg) {
			M.animate(
				svg,
				{ clipPath: ['inset(0 100% 0 0)', 'inset(0 0% 0 0)'] },
				{ duration: 1.1, ease: SUAVE, delay: 0.2 }
			);
		});
		lista('.gf-anel__fatia').forEach(function (c, i) {
			var fim = c.getAttribute('stroke-dasharray');
			M.animate(
				c,
				{ strokeDasharray: ['0 100', fim] },
				{ duration: 0.9, ease: SUAVE, delay: 0.3 + i * 0.12 }
			);
		});
	}
	// As entradas esperam pelo fim do ecrã de carregamento (inc/loader.php).
	var pronto = window.clinicaPronto || Promise.resolve();
	document.addEventListener('clinica:vista', function (e) {
		if ('financas' === e.detail) {
			pronto.then(entrar);
		}
	});
	pronto.then(function () {
		if (!vista.hidden) {
			entrar();
		}
	});

	/* Pesquisa: filtra os pagamentos recentes pelo nome do paciente ou médico. */
	var campo = document.getElementById('fx-procura'),
		itens = lista('#fx-lista .fx-item');
	var nada = document.getElementById('fx-lista-nada'),
		todos = vista.querySelector('[data-fx-todos]');
	var aberto = false;
	function filtrar() {
		var q = campo ? campo.value.trim().toLowerCase() : '',
			n = 0;
		itens.forEach(function (li) {
			var ok = q
				? (li.getAttribute('data-q') || '').indexOf(q) !== -1
				: aberto || !li.classList.contains('is-extra');
			li.hidden = !ok;
			if (ok) {
				n++;
			}
		});
		if (nada) {
			nada.hidden = n > 0 || !q;
		}
	}
	if (campo) {
		campo.addEventListener('input', filtrar);
	}
	if (todos) {
		todos.addEventListener('click', function () {
			aberto = !aberto;
			todos.textContent = aberto ? 'Ver menos' : 'Ver todos';
			todos.setAttribute('aria-expanded', aberto ? 'true' : 'false');
			filtrar();
			if (aberto && mover) {
				M.animate(
					lista('#fx-lista .is-extra'),
					{ opacity: [0, 1], x: [-8, 0] },
					{ duration: 0.35, delay: M.stagger(0.05) }
				);
			}
		});
	}

	/* Cartão a inclinar com o rato (mola), como um objeto físico. */
	lista('[data-fx-inclina]').forEach(function (el) {
		if (!mover) {
			return;
		}
		el.addEventListener('pointermove', function (e) {
			var r = el.getBoundingClientRect();
			var x = (e.clientX - r.left) / r.width - 0.5,
				y = (e.clientY - r.top) / r.height - 0.5;
			// O reflexo segue o rato, como num plástico com brilho.
			el.style.setProperty('--gx', ((x + 0.5) * 100).toFixed(1) + '%');
			el.style.setProperty('--gy', ((y + 0.5) * 100).toFixed(1) + '%');
			M.animate(el, { rotateY: x * 14, rotateX: -y * 12, scale: 1.03 }, MOLA);
		});
		el.addEventListener('pointerleave', function () {
			M.animate(el, { rotateY: 0, rotateX: 0, scale: 1 }, MOLA);
		});
	});

	/* Carrossel das contas: cartão bancário e carteiras móveis. */
	var carrossel = vista.querySelector('[data-fx-carrossel]');
	if (carrossel) {
		var slides = lista('[data-fx-slide]', carrossel),
			pontos = lista('[data-fx-ir]', carrossel),
			atual = 0;
		pontos.forEach(function (p) {
			p.addEventListener('click', function () {
				var k = parseInt(p.getAttribute('data-fx-ir'), 10);
				if (k === atual) {
					return;
				}
				var dir = k > atual ? 1 : -1;
				slides[atual].hidden = true;
				slides[k].hidden = false;
				if (mover) {
					M.animate(
						slides[k],
						{ opacity: [0, 1], x: [40 * dir, 0] },
						{ duration: 0.45, ease: SUAVE }
					);
				}
				pontos.forEach(function (o, i) {
					o.setAttribute('aria-selected', i === k ? 'true' : 'false');
				});
				atual = k;
			});
		});
	}
})();
