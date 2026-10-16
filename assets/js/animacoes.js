/**
 * Animações do site público (linguagem 2026) com Motion — a versão em JS puro
 * do Framer Motion (mesma equipa, mesma API: animate, inView, scroll, stagger).
 * Biblioteca alojada no tema: assets/js/vendor/motion-11.18.2.js (MIT).
 *
 * Organização (cada bloco só atua se encontrar os seus atributos na página):
 *   1. Tokens de movimento: molas, curvas e atrasos num só sítio
 *   2. Revelar ao entrar no ecrã: [data-revelar], [data-revelar-grupo]
 *   3. Entrada do hero: [data-entrada], [data-entrada-foto]
 *   4. Números a contar: [data-contar]
 *   5. Manifesto, palavras a acender: [data-acender]
 *   6. Colagem, aparecer e paralaxe: [data-colagem] > [data-paralaxe]
 *   7. Leque → bento (as 4 fotos descem): [data-leque-vaga] + [data-leque]
 *   8. Perguntas, resposta a deslizar: details.cv-pergunta
 *   8b. Telemóvel ao lado das perguntas: [data-fqt]
 *   9. Vitrine, foto fixa segue a leitura: [data-vitrine] (Especialidades)
 *
 * Melhoria progressiva: o CSS só esconde coisas com html.hp-motion, classe
 * posta no <head> (inc/enqueue.php) e retirada no DOMContentLoaded se este
 * ficheiro não tiver arrancado. Sem JS, com movimento reduzido ou sem Motion,
 * tudo fica no estado final. As entradas esperam pelo ecrã de carregamento.
 */
(function () {
	'use strict';

	var html = document.documentElement;
	var M = window.Motion;

	if (!M || !html.classList.contains('hp-motion')) {
		html.classList.remove('hp-motion');
		return;
	}
	html.classList.add('hp-motion-pronto'); // cancela o plano B de inc/enqueue.php

	// As entradas começam quando o ecrã de carregamento sai (inc/loader.php).
	(window.clinicaPronto || Promise.resolve()).then(iniciar);

	function iniciar() {
		/* 1. Tokens */
		var MOLA = { type: 'spring', stiffness: 120, damping: 20, mass: 1 }; // entradas firmes
		var MOLA_LEQUE = { type: 'spring', stiffness: 70, damping: 13, mass: 1 }; // cartas a cair, com ligeiro ressalto
		var SUAVE = [0.22, 1, 0.36, 1]; // ease-out longo
		var PASSO = 0.08; // atraso entre irmãos
		var AO_ENTRAR = { margin: '0px 0px -12% 0px' }; // um pouco antes do fundo do ecrã
		var ESTREITO = window.matchMedia('(max-width: 640px)').matches;

		// Aceita um elemento, NodeList ou HTMLCollection (o Motion só aceita
		// elemento/NodeList/array — um .children passado tal e qual não animava).
		function revelar(alvos, extra) {
			var lista = alvos instanceof Element ? [alvos] : Array.prototype.slice.call(alvos);
			return M.animate(
				lista,
				{ opacity: [0, 1], y: [24, 0], filter: ['blur(6px)', 'blur(0px)'] },
				Object.assign({ duration: 0.7, ease: SUAVE }, extra || {})
			);
		}

		/* 2. Revelar ao entrar no ecrã (uma vez) */
		M.inView(
			'[data-revelar]',
			function (e) {
				revelar(e.target, {
					delay: parseFloat(e.target.getAttribute('data-revelar')) || 0,
				});
			},
			AO_ENTRAR
		);

		M.inView(
			'[data-revelar-grupo]',
			function (e) {
				revelar(e.target.children, { delay: M.stagger(PASSO) });
			},
			AO_ENTRAR
		);

		/* 3. Entrada do hero (ao carregar) */
		var entrada = document.querySelectorAll('[data-entrada]');
		if (entrada.length) {
			M.animate(
				entrada,
				{ opacity: [0, 1], y: [30, 0] },
				{ duration: 0.9, ease: SUAVE, delay: M.stagger(0.1, { startDelay: 0.15 }) }
			);
		}
		var fotoHero = document.querySelector('[data-entrada-foto]');
		if (fotoHero) {
			M.animate(fotoHero, { scale: [1.08, 1] }, { duration: 1.8, ease: SUAVE });
		}

		/* 4. Números a contar (o HTML já traz o valor final, para quem não tem JS) */
		M.inView(
			'[data-contar]',
			function (e) {
				var el = e.target;
				var alvo = parseFloat(el.getAttribute('data-contar'));
				var sufixo = el.getAttribute('data-sufixo') || '';
				M.animate(0, alvo, {
					duration: 1.4,
					ease: SUAVE,
					onUpdate: function (v) {
						el.textContent = Math.round(v) + sufixo;
					},
				});
			},
			AO_ENTRAR
		);

		/* 5. Manifesto: as palavras acendem com o scroll (reversível) */
		document.querySelectorAll('[data-acender]').forEach(function (el) {
			var palavras = el.querySelectorAll('.ini-palavra');
			el.classList.add('is-pronto');
			M.scroll(
				function (p) {
					var acesas = Math.round(p * palavras.length);
					palavras.forEach(function (w, i) {
						w.classList.toggle('is-acesa', i < acesas);
					});
				},
				{ target: el, offset: ['start 85%', 'end 50%'] }
			);
		});

		/* 6. Colagem: as fotos "saltam" para o lugar e depois flutuam com o scroll */
		document.querySelectorAll('[data-colagem]').forEach(function (colagem) {
			var fotos = colagem.querySelectorAll('[data-paralaxe]');

			M.inView(
				colagem,
				function () {
					M.animate(
						fotos,
						{ opacity: [0, 1], scale: [0.6, 1] },
						Object.assign({}, MOLA, { delay: M.stagger(0.07) })
					);
				},
				{ margin: '0px 0px -20% 0px' }
			);

			fotos.forEach(function (f) {
				var v = parseFloat(f.getAttribute('data-paralaxe')) * (ESTREITO ? 0.4 : 1);
				M.scroll(M.animate(f, { y: [v * 380, v * -380] }, { ease: 'linear' }), {
					target: colagem,
					offset: ['start end', 'end start'],
				});
			});
		});

		/*
		 * 7. Leque → bento
		 * As 4 células pequenas do bento começam "na mão", em leque sobre o título
		 * (posições marcadas pelas vagas invisíveis de .ini-leque), e descem com
		 * mola até ao seu lugar quando a grelha entra no ecrã. Se o visitante voltar
		 * a subir, regressam ao leque (como o whileInView do Framer). Técnica FLIP:
		 * mede-se o lugar final, aplica-se o desvio até à vaga e anima-se até zero.
		 */
		var vagas = document.querySelectorAll('[data-leque-vaga]');
		var cartas = document.querySelectorAll('[data-leque]');
		var grelha = document.querySelector('[data-leque-grelha]');

		if (grelha && ESTREITO) {
			grelha.classList.add('is-leque-pronto'); // no telemóvel é um carrossel: sem leque
		} else if (grelha && vagas.length && vagas.length === cartas.length) {
			var noLeque = true;

			// Desvio de cada carta até à sua vaga, medido com a carta no lugar final.
			var desvios = function () {
				return Array.prototype.map.call(cartas, function (c, i) {
					var antes = c.style.transform;
					c.style.transform = 'none';
					var rc = c.getBoundingClientRect();
					var rv = vagas[i].getBoundingClientRect();
					c.style.transform = antes;
					return {
						x: rv.left + rv.width / 2 - (rc.left + rc.width / 2),
						y: rv.top + rv.height / 2 - (rc.top + rc.height / 2),
						rotate: parseFloat(vagas[i].getAttribute('data-leque-vaga')) || 0,
						scaleX: rv.width / rc.width,
						scaleY: rv.height / rc.height,
					};
				});
			};

			var irParaLeque = function (animar) {
				desvios().forEach(function (d, i) {
					M.animate(
						cartas[i],
						d,
						animar
							? Object.assign({}, MOLA, { delay: (cartas.length - 1 - i) * 0.04 })
							: { duration: 0 }
					);
				});
				noLeque = true;
			};

			irParaLeque(false);
			grelha.classList.add('is-leque-pronto');

			M.inView(
				grelha,
				function () {
					noLeque = false;
					M.animate(
						cartas,
						{ x: 0, y: 0, rotate: 0, scaleX: 1, scaleY: 1 },
						Object.assign({}, MOLA_LEQUE, { delay: M.stagger(0.07) })
					);
					// Ao sair por BAIXO (o visitante voltou a subir), regressa ao leque.
					return function (saida) {
						if (saida.boundingClientRect.top > 0) {
							irParaLeque(true);
						}
					};
				},
				{ margin: '0px 0px -18% 0px' }
			);

			// Ao redimensionar, o leque é recalculado (só se ainda não aterrou).
			var tempo;
			window.addEventListener('resize', function () {
				clearTimeout(tempo);
				tempo = setTimeout(function () {
					if (noLeque) {
						irParaLeque(false);
					}
				}, 150);
			});
		}

		/* 8. Perguntas frequentes: a resposta desliza ao abrir */
		document.querySelectorAll('details.cv-pergunta').forEach(function (d) {
			d.addEventListener('toggle', function () {
				if (d.open) {
					M.animate(
						d.querySelector('p'),
						{ opacity: [0, 1], y: [-6, 0] },
						{ duration: 0.35, ease: SUAVE }
					);
				}
			});
		});

		/* 8b. Telemóvel ao lado das perguntas: a mão sobe com mola e o ecrã
		 * acende peça a peça (o fundo anima por CSS). */
		document.querySelectorAll('[data-fqt]').forEach(function (sec) {
			var tel = sec.querySelector('[data-fqt-tel]'),
				texto = sec.querySelector('[data-fqt-texto]');
			M.inView(
				sec,
				function () {
					if (texto) {
						M.animate(
							Array.prototype.slice.call(texto.children),
							{ opacity: [0, 1], y: [24, 0] },
							{ duration: 0.7, ease: SUAVE, delay: M.stagger(PASSO) }
						);
					}
					if (tel) {
						M.animate(
							tel,
							{ opacity: [0, 1], y: [ESTREITO ? 80 : 170, 0], rotate: [6, 0] },
							{ type: 'spring', stiffness: 70, damping: 15, delay: 0.15 }
						);
						var pecas = Array.prototype.slice
							.call(tel.querySelectorAll('.cv-faq__app > *'))
							.slice(0, 10);
						M.animate(
							pecas,
							{ opacity: [0, 1], y: [14, 0] },
							{
								duration: 0.45,
								ease: SUAVE,
								delay: M.stagger(0.06, { startDelay: 0.6 }),
							}
						);
					}
				},
				AO_ENTRAR
			);
		});

		/*
		 * 9. Vitrine (Especialidades)
		 * A especialidade "em leitura" é a que atravessa a faixa central do ecrã:
		 * fica acesa, a fotografia fixa troca para a dela (com um ligeiro zoom) e a
		 * etiqueta muda de nome. No telemóvel a moldura fixa nem existe (CSS).
		 */
		document.querySelectorAll('[data-vitrine]').forEach(function (vitrine) {
			var fotos = vitrine.querySelectorAll('[data-vitrine-foto]');
			var itens = vitrine.querySelectorAll('[data-vitrine-item]');
			var tab = vitrine.querySelector('.esp-vitrine__tab');
			var nome = vitrine.querySelector('[data-vitrine-nome]');
			var meta = vitrine.querySelector('[data-vitrine-meta]');
			var atual = -1;

			function ativar(i) {
				if (i === atual) {
					return;
				}
				var primeira = atual < 0;
				atual = i;
				itens.forEach(function (it, k) {
					it.classList.toggle('is-ativo', k === i);
				});
				fotos.forEach(function (f, k) {
					f.classList.toggle('is-ativa', k === i);
				});
				if (primeira) {
					return;
				}
				M.animate(fotos[i], { scale: [1.06, 1] }, { duration: 0.9, ease: SUAVE });
				nome.textContent = itens[i].getAttribute('data-nome');
				meta.textContent = itens[i].getAttribute('data-meta');
				M.animate(tab, { opacity: [0, 1], y: [8, 0] }, { duration: 0.4, ease: SUAVE });
			}

			ativar(0);
			itens.forEach(function (it, i) {
				// Devolver uma função mantém o elemento observado (volta a disparar ao regressar).
				M.inView(
					it,
					function () {
						ativar(i);
						return function () {};
					},
					{ margin: '-45% 0px -45% 0px' }
				);
			});
		});
	}
})();
