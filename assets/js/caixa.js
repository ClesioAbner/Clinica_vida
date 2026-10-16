/**
 * Receção · Pagamentos (Caixa). Motion — o Framer Motion em JS puro — dá o
 * movimento; sem Motion ou com movimento reduzido tudo funciona, sem animar.
 *
 *  - Abas com sublinhado que desliza; linhas da lista a entrar em cascata.
 *  - Escolher um pagamento abre a cobrança: total a contar, método com um
 *    indicador que desliza e um palco que ilustra o método:
 *      M-Pesa / e-Mola → mão com telemóvel: pedido do comerciante e teclado do PIN
 *      Cartão          → cartão em 3D com as ondas de aproximação
 *      Numerário       → total, recebido, troco e as notas/moedas a devolver
 *  - Depois de registar, o servidor volta com ?pago=ID: visto desenhado e recibo.
 */
(function () {
	'use strict';

	var raiz = document.querySelector('.cx-checkout');
	var form = document.getElementById('rc-cx-form');
	if (!raiz || !form) {
		return;
	}
	var M = window.Motion;
	var mover =
		!!M &&
		!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
	var MOLA = { type: 'spring', stiffness: 260, damping: 26 };
	var SUAVE = [0.22, 1, 0.36, 1];
	var MOEDA = ' ' + (raiz.getAttribute('data-moeda') || 'MZN');
	var PUSH = '1' === raiz.getAttribute('data-mp-push');
	var MARCA = { mpesa: 'M-Pesa', emola: 'e-Mola' };
	var NOMES = { numerario: 'Numerário', cartao: 'Cartão', mpesa: 'M-Pesa', emola: 'e-Mola' };
	// Notas e moedas de metical (do maior para o menor).
	var NOTAS = [1000, 500, 200, 100, 50, 20],
		MOEDAS = [10, 5, 2, 1];

	var $ = function (id) {
		return document.getElementById(id);
	};
	var q = function (sel) {
		return raiz.querySelector(sel);
	};
	var qa = function (sel, base) {
		return Array.prototype.slice.call((base || raiz).querySelectorAll(sel));
	};
	var vista = raiz.closest('.pm-view');

	var tel = q('.cx-tel'),
		vazio = $('rc-cx-vazio'),
		feito = $('rc-cx-feito'),
		rot = q('[data-palco-rot]');
	var recebido = $('rc-cx-recebido'),
		troco = $('rc-cx-troco'),
		notasR = $('rc-cx-notas'),
		telc = $('rc-cx-tel'),
		go = $('rc-cx-go'),
		hint = $('rc-cx-hint');
	var total = 0,
		metodo = 'numerario',
		escolhido = null;
	var cenaAtual = (q('.cx-cena:not([hidden])') || q('[data-cena="movel"]')).getAttribute(
		'data-cena'
	);

	function mz(v) {
		return String(Math.round(v)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + MOEDA;
	}
	function poe(sel, txt) {
		qa(sel).forEach(function (el) {
			el.textContent = txt;
		});
	}
	function contar(sel, alvo) {
		var els = qa(sel);
		if (!mover) {
			els.forEach(function (el) {
				el.textContent = mz(alvo);
			});
			return;
		}
		M.animate(0, alvo, {
			duration: 0.9,
			ease: SUAVE,
			onUpdate: function (v) {
				els.forEach(function (el) {
					el.textContent = mz(v);
				});
			},
		});
	}
	function rotulo(t) {
		if (!rot || rot.textContent === t) {
			return;
		}
		rot.textContent = t;
		if (mover) {
			M.animate(rot, { opacity: [0, 1], y: [-6, 0] }, { duration: 0.3, ease: SUAVE });
		}
	}

	/* Abas */
	var abas = $('rc-paytabs'),
		linha = abas ? abas.querySelector('.cx-abas__linha') : null;
	function moverLinha(b, instantaneo) {
		if (!linha || !b) {
			return;
		}
		var x = b.offsetLeft,
			w = b.offsetWidth;
		if (!mover || instantaneo) {
			linha.style.transform = 'translateX(' + x + 'px)';
			linha.style.width = w + 'px';
			return;
		}
		M.animate(linha, { x: x, width: w }, MOLA);
	}
	if (abas) {
		qa('.cx-aba', abas).forEach(function (b) {
			b.addEventListener('click', function () {
				qa('.cx-aba', abas).forEach(function (o) {
					o.setAttribute('aria-selected', o === b ? 'true' : 'false');
				});
				moverLinha(b);
				var painel = document.querySelector(
					'.rc-paypanel[data-tab="' + b.dataset.tab + '"]'
				);
				if (mover && painel) {
					M.animate(
						painel,
						{ opacity: [0, 1], y: [8, 0] },
						{ duration: 0.3, ease: SUAVE }
					);
				}
			});
		});
	}

	/* Palco */
	function entrarCena(el, nome) {
		if (!mover || !el) {
			return;
		}
		M.animate(el, { opacity: [0, 1] }, { duration: 0.25 });
		if ('movel' === nome) {
			M.animate(
				el.querySelector('.cx-mao'),
				{ opacity: [0, 1], y: [50, 0], rotate: [5, 0] },
				{ type: 'spring', stiffness: 150, damping: 18 }
			);
			M.animate(
				tel,
				{ opacity: [0, 1], y: [50, 0], rotate: [5, 0] },
				{ type: 'spring', stiffness: 150, damping: 18 }
			);
		} else if ('cartao' === nome) {
			M.animate(
				el.querySelector('.cx-bci'),
				{
					opacity: [0, 1],
					rotateY: [-40, 0],
					rotateX: [16, 0],
					y: [30, 0],
					transformPerspective: 900,
				},
				{ type: 'spring', stiffness: 110, damping: 14 }
			);
			M.animate(
				el.querySelector('.cx-cartao__info'),
				{ opacity: [0, 1], y: [12, 0] },
				{ duration: 0.45, delay: 0.25, ease: SUAVE }
			);
		} else if ('cash' === nome) {
			M.animate(
				qa('.cx-cash > *', el),
				{ opacity: [0, 1], y: [14, 0] },
				{ duration: 0.4, ease: SUAVE, delay: M.stagger(0.05) }
			);
		} else if ('ok' === nome) {
			M.animate(
				el.querySelector('.cx-ok__ic'),
				{ scale: [0.4, 1], opacity: [0, 1] },
				{ type: 'spring', stiffness: 220, damping: 14 }
			);
			M.animate(
				el.querySelector('.cx-ok__aro'),
				{ strokeDasharray: ['0 100', '100 100'] },
				{ duration: 0.6, ease: SUAVE }
			);
			M.animate(
				el.querySelector('.cx-ok__visto'),
				{ strokeDasharray: ['0 100', '100 100'] },
				{ duration: 0.45, delay: 0.45, ease: SUAVE }
			);
			M.animate(
				qa('.cx-ok__t, .cx-ok__v, .cx-ok__s', el),
				{ opacity: [0, 1], y: [10, 0] },
				{ duration: 0.4, delay: M.stagger(0.08, { startDelay: 0.6 }) }
			);
		}
	}
	function cena(nome) {
		if (nome === cenaAtual) {
			return;
		}
		var sai = q('.cx-cena[data-cena="' + cenaAtual + '"]'),
			entra = q('.cx-cena[data-cena="' + nome + '"]');
		cenaAtual = nome;
		var mostrar = function () {
			if (sai) {
				sai.hidden = true;
			}
			entra.hidden = false;
			entrarCena(entra, nome);
		};
		if (mover && sai && !sai.hidden) {
			M.animate(sai, { opacity: [1, 0], scale: [1, 0.97] }, { duration: 0.16 }).then(mostrar);
		} else {
			mostrar();
		}
	}
	function ecra(qual, tema) {
		var ini = q('[data-ecra="idle"]'),
			ped = q('[data-ecra="pedido"]'),
			mudou = ped.hidden;
		tel.setAttribute('data-tema', tema);
		ini.hidden = 'pedido' === qual;
		ped.hidden = 'pedido' !== qual;
		if ('pedido' !== qual) {
			return;
		}
		poe('[data-tel-marca]', MARCA[tema]);
		poe('[data-tel-ini]', MARCA[tema].charAt(0));
		if (!mover) {
			return;
		}
		if (mudou) {
			M.animate(ped, { opacity: [0, 1] }, { duration: 0.25 });
		}
		M.animate(
			qa('.cx-tel__topo, .cx-tel__cartao, .cx-tel__pin', ped),
			{ opacity: [0, 1], y: [14, 0] },
			{ duration: 0.4, ease: SUAVE, delay: M.stagger(0.07, { startDelay: 0.1 }) }
		);
		var n = q('.cx-tel__notif');
		M.animate(
			n,
			{ y: ['-150%', '0%'], opacity: [0, 1] },
			{ type: 'spring', stiffness: 280, damping: 22, delay: 0.35 }
		).then(function () {
			M.animate(n, { y: '-150%', opacity: 0 }, { duration: 0.35, delay: 1.9 });
		});
	}

	/* Numerário */
	// Troco em notas e moedas (do maior para o menor).
	function decompor(v) {
		var r = [],
			resto = Math.round(v);
		NOTAS.concat(MOEDAS).forEach(function (d) {
			var n = Math.floor(resto / d);
			if (n > 0) {
				r.push({ d: d, n: n, moeda: MOEDAS.indexOf(d) !== -1 });
				resto -= n * d;
			}
		});
		return r;
	}
	var ultimoTroco;
	function desenharNotas(t) {
		var caixa = q('[data-cash-notas]'),
			dica = q('[data-cash-dica]');
		if (t === ultimoTroco) {
			return;
		}
		ultimoTroco = t;
		caixa.innerHTML = '';
		if (null === t || t < 0) {
			dica.hidden = false;
			dica.textContent =
				null === t
					? 'Escreva o valor recebido para ver as notas a devolver.'
					: 'O valor recebido não chega para o total.';
			return;
		}
		if (0 === t) {
			dica.hidden = false;
			dica.textContent = 'Valor exato. Não há troco a dar.';
			return;
		}
		dica.hidden = true;
		decompor(t).forEach(function (g) {
			var el = document.createElement('span');
			el.className = 'cx-dinh' + (g.moeda ? ' cx-dinh--moeda' : '');
			el.setAttribute('data-d', g.d);
			var pilha = document.createElement('span');
			pilha.className = 'cx-dinh__pilha';
			for (var i = 0; i < Math.min(g.n, 3); i++) {
				var f = document.createElement('i');
				f.textContent = g.d;
				pilha.appendChild(f);
			}
			var lab = document.createElement('small');
			lab.textContent = g.n + ' × ' + g.d + ' MT';
			el.appendChild(pilha);
			el.appendChild(lab);
			caixa.appendChild(el);
		});
		if (mover) {
			M.animate(
				qa('.cx-dinh', caixa),
				{ opacity: [0, 1], y: [-22, 0], rotate: [-6, 0], scale: [0.85, 1] },
				{ type: 'spring', stiffness: 300, damping: 18, delay: M.stagger(0.06) }
			);
		}
	}
	function calcular() {
		if ('numerario' !== metodo) {
			return;
		}
		var r = parseFloat(recebido.value),
			linhaT = q('.cx-cash__linha--troco');
		if (isNaN(r) || !recebido.value) {
			troco.textContent = '—';
			troco.className = '';
			go.disabled = false;
			poe('[data-cash-rec]', '—');
			poe('[data-cash-troco]', '—');
			if (linhaT) {
				linhaT.classList.remove('is-falta');
			}
			desenharNotas(null);
			return;
		}
		var t = r - total;
		troco.textContent = t >= 0 ? mz(t) : 'Faltam ' + mz(-t);
		troco.className = t >= 0 ? 'is-ok' : 'is-falta';
		go.disabled = t < 0;
		poe('[data-cash-rec]', mz(r));
		poe('[data-cash-troco]', t >= 0 ? mz(t) : 'Faltam ' + mz(-t));
		if (linhaT) {
			linhaT.classList.toggle('is-falta', t < 0);
		}
		desenharNotas(t >= 0 ? t : -1);
	}

	/* Método */
	var ind = form.querySelector('.cx-metodos__ind');
	function moverInd(b, instantaneo) {
		if (!ind || !b || !b.offsetWidth) {
			return;
		}
		var x = b.offsetLeft,
			y = b.offsetTop,
			w = b.offsetWidth,
			h = b.offsetHeight;
		ind.style.setProperty(
			'--cor',
			getComputedStyle(b).getPropertyValue('--met').trim() || '#155E63'
		);
		if (!mover || instantaneo) {
			ind.style.transform = 'translate(' + x + 'px,' + y + 'px)';
			ind.style.width = w + 'px';
			ind.style.height = h + 'px';
			return;
		}
		M.animate(ind, { x: x, y: y, width: w, height: h }, MOLA);
	}
	function escolherMetodo(m, instantaneo) {
		metodo = m;
		$('rc-cx-metodo').value = m;
		var botao = null;
		form.querySelectorAll('.cx-met-b').forEach(function (b) {
			var on = b.dataset.metodo === m;
			b.setAttribute('aria-checked', on ? 'true' : 'false');
			if (on) {
				botao = b;
			}
		});
		moverInd(botao, instantaneo);
		// Só os campos do método escolhido seguem no envio.
		form.querySelectorAll('.cx-extra').forEach(function (x) {
			var on = x.dataset.para.split(' ').indexOf(m) !== -1;
			x.hidden = !on;
			x.querySelectorAll('input').forEach(function (i) {
				i.disabled = !on;
			});
			if (on && mover && !instantaneo) {
				M.animate(x, { opacity: [0, 1], y: [8, 0] }, { duration: 0.3, ease: SUAVE });
			}
		});
		var movel = 'mpesa' === m || 'emola' === m,
			push = 'mpesa' === m && PUSH;
		var ref = form.querySelector('[data-ref-movel]');
		if (ref) {
			ref.hidden = push;
			ref.querySelector('input').disabled = !movel || push;
		}
		telc.required = push;
		$('rc-cx-acao').value = push ? 'pg_cobrar' : 'pagamento';
		go.textContent = push
			? 'Cobrar via M-Pesa'
			: movel
				? 'Registar recebido'
				: 'cartao' === m
					? 'Registar pagamento'
					: 'Confirmar pagamento';
		hint.textContent = push
			? 'O cliente recebe o pedido de PIN no telemóvel. Pode demorar até 1 minuto.'
			: movel
				? 'Registe depois de receber o SMS de confirmação do ' + MARCA[m] + '.'
				: 'cartao' === m
					? 'Registe depois de o terminal aprovar.'
					: '';
		go.disabled = false;
		rotulo(NOMES[m] + (push ? ', pedido de PIN' : movel ? ', registo manual' : ''));
		if (movel) {
			cena('movel');
			ecra('pedido', m);
		} else if ('cartao' === m) {
			cena('cartao');
		} else {
			cena('cash');
		}
		calcular();
	}

	/* Abrir */
	function abrir(b) {
		escolhido = b;
		qa('.cx-item', document).forEach(function (o) {
			o.setAttribute('aria-pressed', o === b ? 'true' : 'false');
		});
		total = parseFloat(b.dataset.valor) || 0;
		$('rc-cx-id').value = b.dataset.pg;
		$('rc-cx-ini').textContent = b.dataset.ini;
		$('rc-cx-nome').textContent = b.dataset.nome;
		$('rc-cx-det').textContent = b.dataset.det;
		poe('[data-cartao-nome]', b.dataset.nome.toUpperCase());
		poe('[data-tel-num]', '—');
		contar('#rc-cx-total, [data-tel-valor], [data-cartao-valor], [data-cash-total]', total);
		// Atalhos de valor recebido: o exato e as notas redondas acima do total.
		notasR.innerHTML = '';
		var vals = [total];
		[500, 1000, 2000, 5000].forEach(function (n) {
			var a = Math.ceil(total / n) * n;
			if (a > total && vals.indexOf(a) === -1) {
				vals.push(a);
			}
		});
		vals.slice(0, 4).forEach(function (v, i) {
			var n = document.createElement('button');
			n.type = 'button';
			n.className = 'cx-nota-r';
			n.textContent = 0 === i ? 'Valor exato' : mz(v);
			n.addEventListener('click', function () {
				recebido.value = v;
				calcular();
			});
			notasR.appendChild(n);
		});
		recebido.value = '';
		telc.value = '';
		ultimoTroco = undefined;
		form.querySelectorAll('input[name="referencia"]').forEach(function (i) {
			i.value = '';
		});
		vazio.hidden = true;
		if (feito) {
			feito.hidden = true;
		}
		var novo = form.hidden;
		form.hidden = false;
		if (novo && mover) {
			M.animate(
				qa('.cx-form > *', form),
				{ opacity: [0, 1], y: [10, 0] },
				{ duration: 0.35, ease: SUAVE, delay: M.stagger(0.035) }
			);
		}
		escolherMetodo('numerario', true);
		if (window.matchMedia('(max-width: 1280px)').matches) {
			raiz.scrollIntoView({ behavior: mover ? 'smooth' : 'auto', block: 'start' });
		}
	}

	qa('.cx-item', document).forEach(function (b) {
		b.addEventListener('click', function () {
			abrir(b);
		});
	});
	form.querySelectorAll('.cx-met-b').forEach(function (b) {
		b.addEventListener('click', function () {
			escolherMetodo(b.dataset.metodo);
		});
	});
	recebido.addEventListener('input', calcular);
	telc.addEventListener('input', function () {
		poe('[data-tel-num]', telc.value.trim() || '—');
	});
	window.addEventListener('resize', function () {
		moverInd(form.querySelector('.cx-met-b[aria-checked="true"]'), true);
		moverLinha(abas && abas.querySelector('.cx-aba[aria-selected="true"]'), true);
	});

	form.addEventListener('submit', function (e) {
		if (!escolhido) {
			e.preventDefault();
			return;
		}
		if (telc.required && !telc.value.trim()) {
			e.preventDefault();
			telc.focus();
			return;
		}
		if (
			!window.confirm(
				go.textContent + ': ' + $('rc-cx-nome').textContent + ' · ' + mz(total) + '?'
			)
		) {
			e.preventDefault();
			return;
		}
		go.disabled = true;
		go.classList.add('is-aguarda');
		go.textContent = 'A registar…';
		if ('pg_cobrar' === $('rc-cx-acao').value) {
			var w = $('rc-mpwait');
			if (w) {
				w.querySelector('.rc-mpwait__num').textContent =
					telc.value.trim() + '  ·  ' + mz(total);
				w.hidden = false;
				requestAnimationFrame(function () {
					w.classList.add('is-on');
				});
			}
		}
	});

	// Depois de registar: "Cobrar outro pagamento" volta ao início da caixa.
	var proximo = raiz.querySelector('[data-cx-proximo]');
	if (proximo) {
		proximo.addEventListener('click', function () {
			feito.hidden = true;
			vazio.hidden = false;
			ecra('idle', 'idle');
			rotulo('Caixa pronta');
			cena('movel');
			if (mover) {
				M.animate(vazio, { opacity: [0, 1], y: [10, 0] }, { duration: 0.35, ease: SUAVE });
			}
		});
	}

	// Cartão: inclina com o rato e o brilho segue o cursor.
	var cartao = q('.cx-bci');
	if (cartao && mover) {
		cartao.addEventListener('pointermove', function (e) {
			var r = cartao.getBoundingClientRect(),
				x = (e.clientX - r.left) / r.width,
				y = (e.clientY - r.top) / r.height;
			cartao.style.setProperty('--gx', x * 100 + '%');
			cartao.style.setProperty('--gy', y * 100 + '%');
			M.animate(
				cartao,
				{ rotateY: (x - 0.5) * 18, rotateX: (0.5 - y) * 14, transformPerspective: 900 },
				{ type: 'spring', stiffness: 170, damping: 18 }
			);
		});
		cartao.addEventListener('pointerleave', function () {
			M.animate(
				cartao,
				{ rotateY: 0, rotateX: 0 },
				{ type: 'spring', stiffness: 170, damping: 18 }
			);
		});
	}

	/* Entrada */
	var primeira = true;
	function mostrarCaixa() {
		if (!primeira || (vista && vista.hidden)) {
			return;
		}
		primeira = false;
		moverLinha(abas && abas.querySelector('.cx-aba[aria-selected="true"]'), true);
		moverInd(form.querySelector('.cx-met-b[aria-checked="true"]'), true);
		entrarCena(q('.cx-cena[data-cena="' + cenaAtual + '"]'), cenaAtual);
		if (mover) {
			M.animate(
				qa('.cx-itens > li', document).slice(0, 12),
				{ opacity: [0, 1], x: [-14, 0] },
				{ duration: 0.4, ease: SUAVE, delay: M.stagger(0.045, { startDelay: 0.15 }) }
			);
		}
	}
	document.addEventListener('clinica:vista', function (e) {
		if ('pagamentos' === e.detail) {
			setTimeout(mostrarCaixa, 30);
		}
	});
	mostrarCaixa();

	// Vindo da marcação ("Cobrar logo a seguir") ou do painel: abre esse pagamento.
	var m = /[?&]pg=(\d+)/.exec(location.search);
	if (m && !/[?&]pago=/.test(location.search)) {
		var alvo = document.querySelector('.cx-item[data-pg="' + m[1] + '"]');
		if (alvo) {
			abrir(alvo);
		}
	}
})();
