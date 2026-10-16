/**
 * Camada JS partilhada do tema (carregada com defer em todo o site).
 *
 * Tudo aqui é melhoria progressiva: sem JS, os formulários continuam a
 * submeter e o servidor valida na mesma. Nada aqui é obrigatório para marcar.
 */
(function () {
	'use strict';

	/**
	 * Barra de pesquisa da homepage: mostra só os médicos da especialidade
	 * escolhida. Sem JS, a lista mostra todos e o /marcar/ filtra à chegada.
	 */
	function filtrarMedicosPorEspecialidade(form) {
		var esp = form.querySelector('[data-papel="especialidade"]');
		var med = form.querySelector('[data-papel="medico"]');

		if (!esp || !med) {
			return;
		}

		function aplicar() {
			var escolhida = esp.value;
			var visiveis = 0;

			Array.prototype.forEach.call(med.options, function (opcao) {
				if (!opcao.value) {
					return; // "Qualquer médico" fica sempre.
				}
				var pertence = !escolhida || String(opcao.dataset.esp) === String(escolhida);
				opcao.hidden = !pertence;
				if (pertence) {
					visiveis++;
				}
			});

			// Se o médico escolhido deixou de pertencer à especialidade, larga-o.
			if (med.selectedOptions.length && med.selectedOptions[0].hidden) {
				med.value = '';
			}

			med.options[0].textContent = visiveis
				? 'Qualquer médico'
				: 'Sem médicos nesta especialidade';
		}

		esp.addEventListener('change', aplicar);
		aplicar();
	}

	document.querySelectorAll('form[data-filtrar-medicos]').forEach(filtrarMedicosPorEspecialidade);

	/**
	 * Menu móvel do cabeçalho público: as três barras abrem uma gaveta que
	 * desliza da direita, com fundo escurecido. Enquanto aberta, a página não
	 * desliza, o foco fica dentro da gaveta (Tab circula) e o resto é inert.
	 * Fecha com o X, Esc, toque no fundo, escolha de um destino ou ao arrastar
	 * a gaveta para a direita. Sem JS, o rodapé continua a ter todos os links.
	 */
	function menuMovel(botao) {
		var gaveta = document.getElementById(botao.getAttribute('aria-controls'));
		var fundo = document.querySelector('[data-gaveta-fundo]');
		var rotulo = botao.querySelector('.hp-sr-only');
		var html = document.documentElement;

		if (!gaveta) {
			return;
		}

		function focaveis() {
			return Array.prototype.slice.call(
				gaveta.querySelectorAll('a[href], button:not([disabled])')
			);
		}

		function definir(aberta, devolverFoco) {
			botao.setAttribute('aria-expanded', aberta ? 'true' : 'false');
			gaveta.classList.toggle('is-aberta', aberta);
			gaveta.setAttribute('aria-hidden', aberta ? 'false' : 'true');
			gaveta.inert = !aberta;
			gaveta.style.transform = '';
			if (fundo) {
				fundo.classList.toggle('is-aberto', aberta);
			}
			html.classList.toggle('hp-gaveta-aberta', aberta);
			if (rotulo) {
				rotulo.textContent = aberta ? 'Fechar menu' : 'Abrir menu';
			}
			if (aberta) {
				// Depois da transição começar, o foco vai para o primeiro destino.
				setTimeout(function () {
					var f = focaveis();
					if (f[1]) {
						f[1].focus({ preventScroll: true });
					}
				}, 60);
			} else if (devolverFoco) {
				botao.focus({ preventScroll: true });
			}
		}

		botao.addEventListener('click', function () {
			definir('true' !== botao.getAttribute('aria-expanded'));
		});
		gaveta.querySelectorAll('[data-gaveta-fechar]').forEach(function (b) {
			b.addEventListener('click', function () {
				definir(false, true);
			});
		});
		if (fundo) {
			fundo.addEventListener('click', function () {
				definir(false, true);
			});
		}

		// Escolher um destino fecha a gaveta (útil em âncoras da mesma página).
		gaveta.addEventListener('click', function (ev) {
			if (ev.target.closest('a[href]')) {
				definir(false);
			}
		});

		// Esc fecha; Tab circula dentro da gaveta aberta.
		document.addEventListener('keydown', function (ev) {
			if (!gaveta.classList.contains('is-aberta')) {
				return;
			}
			if ('Escape' === ev.key) {
				definir(false, true);
				return;
			}
			if ('Tab' === ev.key) {
				var f = focaveis(),
					prim = f[0],
					ult = f[f.length - 1];
				if (ev.shiftKey && document.activeElement === prim) {
					ev.preventDefault();
					ult.focus();
				} else if (!ev.shiftKey && document.activeElement === ult) {
					ev.preventDefault();
					prim.focus();
				}
			}
		});

		// Arrastar para a direita fecha (segue o dedo; abaixo de 80px volta ao lugar).
		var x0 = null,
			dx = 0;
		gaveta.addEventListener(
			'touchstart',
			function (ev) {
				x0 = ev.touches[0].clientX;
				dx = 0;
				gaveta.classList.add('is-arrastar');
			},
			{ passive: true }
		);
		gaveta.addEventListener(
			'touchmove',
			function (ev) {
				if (null === x0) {
					return;
				}
				dx = Math.max(0, ev.touches[0].clientX - x0);
				gaveta.style.transform = 'translateX(' + dx + 'px)';
			},
			{ passive: true }
		);
		gaveta.addEventListener('touchend', function () {
			gaveta.classList.remove('is-arrastar');
			if (dx > 80) {
				definir(false, true);
			} else {
				gaveta.style.transform = '';
			}
			x0 = null;
		});

		// Ao alargar a janela para desktop, a gaveta não pode ficar presa aberta.
		window.matchMedia('(min-width: 961px)').addEventListener('change', function (mq) {
			if (mq.matches) {
				definir(false);
			}
		});
	}

	document.querySelectorAll('[data-menu-toggle]').forEach(menuMovel);

	// Efeitos ligados ao scroll (manifesto, colagem, leque) vivem em animacoes.js (Motion).
	var semMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* Carrossel (testemunhos): o deslizar é scroll-snap nativo; os botões só
	 * avançam um cartão e desligam-se nas pontas. */
	function carrossel(raiz) {
		var pista = raiz.querySelector('[data-carrossel-pista]');
		var ant = raiz.querySelector('[data-carrossel-ant]');
		var seg = raiz.querySelector('[data-carrossel-seg]');
		if (!pista || !ant || !seg) {
			return;
		}
		function passo() {
			var slide = pista.firstElementChild;
			return slide ? slide.getBoundingClientRect().width + 12 : pista.clientWidth;
		}
		function estado() {
			ant.disabled = pista.scrollLeft < 4;
			seg.disabled = pista.scrollLeft + pista.clientWidth >= pista.scrollWidth - 4;
		}
		ant.addEventListener('click', function () {
			pista.scrollBy({ left: -passo(), behavior: semMovimento ? 'auto' : 'smooth' });
		});
		seg.addEventListener('click', function () {
			pista.scrollBy({ left: passo(), behavior: semMovimento ? 'auto' : 'smooth' });
		});
		pista.addEventListener('scroll', estado, { passive: true });
		estado();
	}

	document.querySelectorAll('[data-carrossel]').forEach(carrossel);

	/* Listas que no telemóvel deslizam (especialidades): pontos de posição por
	 * baixo — só aparecem quando a lista desliza mesmo — e, se houver um índice
	 * com âncoras para os cartões, os botões levam ao cartão em vez de saltar. */
	function deslizar(pista) {
		// Cartões visíveis, pela ordem no ecrã (contentores com display:contents
		// contam pelos filhos; a propriedade order pode mudar a ordem).
		var itens = [];
		Array.prototype.forEach.call(pista.children, function (el) {
			if ('contents' === getComputedStyle(el).display) {
				Array.prototype.push.apply(itens, el.children);
			} else {
				itens.push(el);
			}
		});
		itens = itens
			.filter(function (el) {
				return el.getClientRects().length;
			})
			.sort(function (a, b) {
				return a.getBoundingClientRect().left - b.getBoundingClientRect().left;
			});
		// Distância (px) do cartão ao início visível da pista.
		function desvio(it) {
			return (
				it.getBoundingClientRect().left -
				pista.getBoundingClientRect().left -
				(parseFloat(getComputedStyle(pista).paddingLeft) || 0)
			);
		}
		if (itens.length < 2) {
			return;
		}
		var pontos = document.createElement('div');
		pontos.className = 'hp-pontos';
		pontos.setAttribute('role', 'group');
		pontos.setAttribute('aria-label', 'Posição na lista');
		itens.forEach(function (it, i) {
			var b = document.createElement('button');
			b.type = 'button';
			b.setAttribute('aria-label', 'Ir para o cartão ' + (i + 1) + ' de ' + itens.length);
			b.addEventListener('click', function () {
				irPara(it);
			});
			pontos.appendChild(b);
		});
		pista.insertAdjacentElement('afterend', pontos);

		function irPara(it) {
			pista.scrollTo({
				left: pista.scrollLeft + desvio(it),
				behavior: semMovimento ? 'auto' : 'smooth',
			});
		}
		var indice = document.querySelectorAll('a[href^="#"]');
		function atual() {
			var melhor = 0,
				dist = Infinity;
			itens.forEach(function (it, i) {
				var d = Math.abs(desvio(it));
				if (d < dist) {
					dist = d;
					melhor = i;
				}
			});
			pontos.querySelectorAll('button').forEach(function (b, i) {
				b.setAttribute('aria-current', i === melhor ? 'true' : 'false');
			});
			var id = itens[melhor].id;
			if (id) {
				indice.forEach(function (a) {
					if (a.getAttribute('href') === '#' + id) {
						a.setAttribute('aria-current', 'true');
					} else if (
						itens.some(function (it) {
							return '#' + it.id === a.getAttribute('href');
						})
					) {
						a.removeAttribute('aria-current');
					}
				});
			}
		}
		var t;
		pista.addEventListener(
			'scroll',
			function () {
				clearTimeout(t);
				t = setTimeout(atual, 60);
			},
			{ passive: true }
		);
		window.addEventListener('resize', atual);
		atual();

		// Índice (ex.: botões com o nome da especialidade): leva ao cartão.
		indice.forEach(function (a) {
			var alvo = itens.filter(function (it) {
				return it.id && '#' + it.id === a.getAttribute('href');
			})[0];
			if (!alvo) {
				return;
			}
			a.addEventListener('click', function (ev) {
				if (pista.scrollWidth <= pista.clientWidth + 4) {
					return;
				} // não desliza (computador): âncora normal
				ev.preventDefault();
				irPara(alvo);
				pista.scrollIntoView({
					behavior: semMovimento ? 'auto' : 'smooth',
					block: 'nearest',
				});
			});
		});
	}

	document.querySelectorAll('[data-deslizar]').forEach(deslizar);
})();
