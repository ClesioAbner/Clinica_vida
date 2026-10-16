/**
 * Direção (forma "Gentelella"): animações com Motion — o Framer Motion em JS
 * puro, alojado no tema (assets/js/vendor/motion-11.18.2.js).
 *
 *   1. Entrada de cada vista: blocos em cascata e números a contar.
 *   2. Gráficos a desenhar-se: áreas, colunas, barras, anel e mostrador.
 *   3. Profundidade (3D) onde ajuda: números e mostrador inclinam com o rato.
 *   4. Painéis que recolhem e fecham, como no original.
 *
 * As vistas começam escondidas: as entradas correm quando o portal mostra uma
 * (evento "clinica:vista"). Com movimento reduzido ou sem Motion, fica tudo no
 * estado final e os painéis continuam a recolher e a fechar.
 */
(function () {
	'use strict';

	var M = window.Motion;
	var mover =
		!!M &&
		!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	var SUAVE = [0.22, 1, 0.36, 1];
	var SAIDA = [0.34, 1.4, 0.64, 1]; // "back out": um pequeno ressalto à chegada
	var MOLA = { type: 'spring', stiffness: 170, damping: 18 };
	var feitas = {};

	function todos(sel, raiz) {
		return Array.prototype.slice.call((raiz || document).querySelectorAll(sel));
	}

	/* Números: "34 mil", "1,2 M", inteiros e percentagens, como o servidor escreve. */
	function formatar(v, fmt) {
		if ('pct' === fmt) {
			return Math.round(v) + '%';
		}
		if ('dec' === fmt) {
			return (Math.round(v * 10) / 10).toFixed(1).replace('.', ',');
		}
		if ('mil' === fmt && v >= 1000) {
			var m = v >= 1e6 ? v / 1e6 : v / 1000;
			var t = (Math.round(m * 10) / 10).toString().replace('.', ',');
			return t + (v >= 1e6 ? ' M' : ' mil');
		}
		return String(Math.round(v)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
	}

	/* 1 + 2. Entrada de uma vista. */
	function entrar(vista) {
		if (!vista || feitas[vista.dataset.view] || !mover) {
			return;
		}
		feitas[vista.dataset.view] = true;

		// Blocos em cascata: a faixa de números e os painéis, linha a linha.
		var grupos = todos('[data-gt-entrada]', vista);
		grupos.forEach(function (g, i) {
			var alvos =
				g.classList.contains('gt-tiles') ||
				g.classList.contains('gt-linha3') ||
				g.classList.contains('gt-linha2')
					? g.children
					: [g];
			M.animate(
				alvos,
				{ opacity: [0, 1], y: [16, 0], scale: [0.98, 1] },
				{ duration: 0.45, ease: SAIDA, delay: M.stagger(0.06, { startDelay: i * 0.1 }) }
			);
		});

		todos('[data-gt-n]', vista).forEach(function (el) {
			var alvo = parseFloat(el.getAttribute('data-gt-n')) || 0,
				fmt = el.getAttribute('data-gt-fmt') || 'int';
			M.animate(0, alvo, {
				duration: 1.3,
				ease: SUAVE,
				onUpdate: function (v) {
					el.textContent = formatar(v, fmt);
				},
			});
		});

		// Áreas e linhas: revelam-se da esquerda para a direita.
		todos('.gf-svg', vista).forEach(function (svg) {
			M.animate(
				svg,
				{ clipPath: ['inset(0 100% 0 0)', 'inset(0 0% 0 0)'] },
				{ duration: 1.2, ease: SUAVE, delay: 0.25 }
			);
		});
		// Colunas sobem a partir do eixo; barras horizontais crescem da esquerda.
		var colunas = todos(
			'.gf-col__barra, .gf-col__pilha, .gf-div__cima i, .gf-div__baixo i',
			vista
		);
		if (colunas.length) {
			M.animate(
				colunas,
				{ scaleY: [0, 1] },
				{ duration: 0.7, ease: SUAVE, delay: M.stagger(0.025, { startDelay: 0.3 }) }
			);
		}
		var barras = todos('[data-gt-barra], .gf-barra__trilho i', vista).filter(function (b) {
			return b.offsetWidth > 0;
		});
		if (barras.length) {
			M.animate(
				barras,
				{ scaleX: [0, 1] },
				{ duration: 0.8, ease: SUAVE, delay: M.stagger(0.06, { startDelay: 0.35 }) }
			);
		}
		// Valores por cima das colunas: aparecem quando a coluna acaba de subir.
		var rotulos = todos('.gf-col__rot, .gf-col__v', vista);
		if (rotulos.length) {
			// Só opacidade: o centramento (translate -50%) vem do CSS.
			M.animate(
				rotulos,
				{ opacity: [0, 1] },
				{ duration: 0.35, delay: M.stagger(0.03, { startDelay: 0.9 }) }
			);
		}
		// Pontos das curvas: surgem com mola depois de a linha passar.
		var pontos = todos('.gf-ponto.is-fixo', vista);
		if (pontos.length) {
			M.animate(
				pontos,
				{ scale: [0, 1] },
				{
					type: 'spring',
					stiffness: 320,
					damping: 14,
					delay: M.stagger(0.04, { startDelay: 1 }),
				}
			);
		}
		// Parte de um total: primeiro o total (claro), depois a parte (escura).
		var totais = todos('.gf-parte__total', vista);
		if (totais.length) {
			M.animate(
				totais,
				{ scaleX: [0, 1] },
				{ duration: 0.7, ease: SUAVE, delay: M.stagger(0.07, { startDelay: 0.3 }) }
			);
			M.animate(
				todos('.gf-parte__feito', vista),
				{ scaleX: [0, 1] },
				{ duration: 0.8, ease: SUAVE, delay: M.stagger(0.07, { startDelay: 0.75 }) }
			);
		}
		// Anel: roda até ao lugar enquanto cada fatia se desenha pela sua vez.
		todos('.gf-anel svg', vista).forEach(function (svg) {
			M.animate(
				svg,
				{ rotate: [-200, -90], scale: [0.86, 1] },
				{ type: 'spring', stiffness: 70, damping: 14, delay: 0.25 }
			);
		});
		todos('.gf-anel__fatia', vista).forEach(function (c, k) {
			M.animate(
				c,
				{ strokeDasharray: ['0 100', c.getAttribute('stroke-dasharray')] },
				{ duration: 0.9, ease: SUAVE, delay: 0.35 + k * 0.15 }
			);
		});
		todos('.gf-anel__centro', vista).forEach(function (c) {
			M.animate(
				c,
				{ opacity: [0, 1], scale: [0.7, 1] },
				{ type: 'spring', stiffness: 200, damping: 16, delay: 0.55 }
			);
		});
		// Mostrador: o arco enche e o ponteiro sobe com mola até ao valor.
		todos('.gf--ponteiro', vista).forEach(function (g) {
			var arco = g.querySelector('.gf-ponteiro__feito'),
				agulha = g.querySelector('.gf-ponteiro__agulha');
			var pct = parseFloat(g.getAttribute('aria-valuenow')) || 0;
			if (arco) {
				M.animate(
					arco,
					{ strokeDasharray: ['0 100', arco.getAttribute('stroke-dasharray')] },
					{ duration: 1.1, ease: SUAVE, delay: 0.4 }
				);
			}
			// Roda à volta do eixo (100,100) e não do centro da linha: o ângulo vai
			// direto para o atributo, com a mola do Motion a dar o balanço.
			if (agulha && pct > 0) {
				agulha.setAttribute('transform', 'rotate(' + -pct * 1.8 + ' 100 100)');
				M.animate(-pct * 1.8, 0, {
					type: 'spring',
					stiffness: 60,
					damping: 9,
					delay: 0.4,
					onUpdate: function (a) {
						agulha.setAttribute('transform', 'rotate(' + a + ' 100 100)');
					},
				});
			}
		});
	}

	// As entradas esperam pelo fim do ecrã de carregamento (inc/loader.php).
	var pronto = window.clinicaPronto || Promise.resolve();
	document.addEventListener('clinica:vista', function (e) {
		pronto.then(function () {
			entrar(document.querySelector('.pm-view[data-view="' + e.detail + '"]'));
		});
	});
	pronto.then(function () {
		todos('.pm-view').forEach(function (v) {
			if (
				!v.hidden &&
				v.dataset.view &&
				(v.classList.contains('gt-painel') || v.querySelector('[data-gt-entrada], .gf'))
			) {
				entrar(v);
			}
		});
	});

	/* 3. Profundidade: inclina com o rato (perspetiva), volta com mola. */
	todos('[data-gt-3d]').forEach(function (el) {
		if (!mover) {
			return;
		}
		el.addEventListener('pointermove', function (e) {
			var r = el.getBoundingClientRect();
			var x = (e.clientX - r.left) / r.width - 0.5,
				y = (e.clientY - r.top) / r.height - 0.5;
			M.animate(
				el,
				{ rotateY: x * 8, rotateX: -y * 8, transformPerspective: 700, y: -2 },
				MOLA
			);
		});
		el.addEventListener('pointerleave', function () {
			M.animate(el, { rotateY: 0, rotateX: 0, y: 0 }, MOLA);
		});
	});

	/* 4. Painéis: recolher (o corpo desliza) e fechar (o painel sai). */
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('[data-gt-recolher], [data-gt-fechar]') : null;
		if (!b) {
			return;
		}
		var painel = b.closest('.gt-x');
		if (!painel) {
			return;
		}
		if (b.hasAttribute('data-gt-fechar')) {
			var sair = function () {
				painel.hidden = true;
			};
			if (mover) {
				M.animate(painel, { opacity: 0, scale: 0.96 }, { duration: 0.25 }).then(sair);
			} else {
				sair();
			}
			return;
		}
		var corpo = painel.querySelector('.gt-x__corpo');
		var aberto = 'true' === b.getAttribute('aria-expanded');
		b.setAttribute('aria-expanded', aberto ? 'false' : 'true');
		b.setAttribute('aria-label', aberto ? 'Abrir painel' : 'Recolher painel');
		painel.classList.toggle('is-recolhido', aberto);
		if (!corpo) {
			return;
		}
		if (aberto) {
			if (mover) {
				M.animate(corpo, { opacity: 0, y: -6 }, { duration: 0.18 }).then(function () {
					corpo.hidden = true;
				});
			} else {
				corpo.hidden = true;
			}
		} else {
			corpo.hidden = false;
			if (mover) {
				M.animate(corpo, { opacity: [0, 1], y: [-6, 0] }, { duration: 0.25, ease: SUAVE });
			}
		}
	});
})();
