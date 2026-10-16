/**
 * Gráficos dos portais: dica partilhada (tooltip) para inc/graficos.php.
 *
 * Cada marca com data-gf-linhas (coluna, zona do cursor, barra) mostra a mesma
 * dica ao passar o rato e ao receber o foco do teclado. Os textos vêm do
 * servidor já formatados e entram com textContent, nunca como HTML.
 */
(function () {
	'use strict';

	var dica = null;

	function criar() {
		dica = document.createElement('div');
		dica.className = 'gf-dica';
		dica.setAttribute('role', 'tooltip');
		document.body.appendChild(dica);
	}

	function preencher(el) {
		var linhas;
		try {
			linhas = JSON.parse(el.getAttribute('data-gf-linhas') || '[]');
		} catch (e) {
			linhas = [];
		}
		dica.textContent = '';
		var titulo = el.getAttribute('data-gf-titulo');
		if (titulo) {
			var t = document.createElement('p');
			t.className = 'gf-dica__t';
			t.textContent = titulo;
			dica.appendChild(t);
		}
		// Com uma série escolhida nas pílulas, a dica mostra só essa.
		var fig = el.closest('.gf');
		var so = fig ? fig.getAttribute('data-gf-so') : null;
		linhas.forEach(function (l) {
			if (null !== so && undefined !== l[3] && String(l[3]) !== so) {
				return;
			}
			var linha = document.createElement('div');
			linha.className = 'gf-dica__l';
			var chave = document.createElement('i');
			if (l[0]) {
				chave.className = 'gf-c--' + l[0];
			}
			var valor = document.createElement('b');
			valor.textContent = l[2];
			var nome = document.createElement('span');
			nome.textContent = l[1];
			linha.appendChild(chave);
			linha.appendChild(valor);
			linha.appendChild(nome);
			dica.appendChild(linha);
		});
	}

	// Colunas e cursor: ao lado da marca (à direita, ou à esquerda se não
	// couber), para nunca tapar o ponto lido. Barras: por cima, centrada.
	function posicionar(el) {
		var r = el.getBoundingClientRect();
		var d = dica.getBoundingClientRect();
		var x = r.left + r.width / 2 - d.width / 2;
		var y = r.top - d.height - 10;
		if (el.classList.contains('gf-col') || el.classList.contains('gf-alvo')) {
			x = r.right + 10;
			if (x + d.width > window.innerWidth - 8) {
				x = r.left - d.width - 10;
			}
			y = r.top + 8;
		}
		x = Math.max(8, Math.min(x, window.innerWidth - d.width - 8));
		if (y < 8) {
			y = r.bottom + 10;
		}
		dica.style.left = Math.round(x) + 'px';
		dica.style.top = Math.round(y) + 'px';
	}

	function mostrar(el) {
		if (!dica) {
			criar();
		}
		preencher(el);
		posicionar(el);
		dica.classList.add('is-visivel');
	}

	function esconder() {
		if (dica) {
			dica.classList.remove('is-visivel');
		}
	}

	function alvo(e) {
		return e.target && e.target.closest ? e.target.closest('[data-gf-linhas]') : null;
	}

	document.addEventListener('pointerover', function (e) {
		var el = alvo(e);
		if (el) {
			mostrar(el);
		}
	});
	document.addEventListener('pointerout', function (e) {
		var el = alvo(e);
		if (el && !el.contains(e.relatedTarget)) {
			esconder();
		}
	});
	document.addEventListener('focusin', function (e) {
		var el = alvo(e);
		if (el) {
			mostrar(el);
		}
	});
	document.addEventListener('focusout', esconder);
	window.addEventListener('scroll', esconder, { passive: true });

	// Pílulas de filtro (.gf-filtro): data-gf-alvo = id da figura, data-so =
	// índice da série ('' = todas).
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.gf-filtro [data-so]') : null;
		if (!b) {
			return;
		}
		var grupo = b.closest('.gf-filtro');
		var fig = document.getElementById(grupo.getAttribute('data-gf-alvo'));
		grupo.querySelectorAll('[data-so]').forEach(function (o) {
			o.setAttribute('aria-pressed', o === b ? 'true' : 'false');
		});
		if (fig) {
			if ('' === b.getAttribute('data-so')) {
				fig.removeAttribute('data-gf-so');
			} else {
				fig.setAttribute('data-gf-so', b.getAttribute('data-so'));
			}
		}
	});

	// Seletores que levam a outro endereço (ex.: período do painel).
	document.addEventListener('change', function (e) {
		var s = e.target;
		if (s && s.matches && s.matches('select[data-gf-ir]') && s.value) {
			window.location.href = s.value;
		}
	});
	document.addEventListener('keydown', function (e) {
		if ('Escape' === e.key) {
			esconder();
		}
	});
})();
