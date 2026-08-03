/**
 * Camada JS partilhada do tema (carregada com defer em todo o site).
 *
 * Tudo aqui é melhoria progressiva: sem JS, os formulários continuam a
 * submeter e o servidor valida na mesma. Nada aqui é obrigatório para marcar.
 */
( function () {
	'use strict';

	/**
	 * Barra de pesquisa da homepage: mostra só os médicos da especialidade
	 * escolhida. Sem JS, a lista mostra todos e o /marcar/ filtra à chegada.
	 */
	function filtrarMedicosPorEspecialidade( form ) {
		var esp = form.querySelector( '[data-papel="especialidade"]' );
		var med = form.querySelector( '[data-papel="medico"]' );

		if ( ! esp || ! med ) {
			return;
		}

		function aplicar() {
			var escolhida = esp.value;
			var visiveis  = 0;

			Array.prototype.forEach.call( med.options, function ( opcao ) {
				if ( ! opcao.value ) {
					return; // "Qualquer médico" fica sempre.
				}
				var pertence = ! escolhida || String( opcao.dataset.esp ) === String( escolhida );
				opcao.hidden = ! pertence;
				if ( pertence ) {
					visiveis++;
				}
			} );

			// Se o médico escolhido deixou de pertencer à especialidade, larga-o.
			if ( med.selectedOptions.length && med.selectedOptions[ 0 ].hidden ) {
				med.value = '';
			}

			med.options[ 0 ].textContent = visiveis ? 'Qualquer médico' : 'Sem médicos nesta especialidade';
		}

		esp.addEventListener( 'change', aplicar );
		aplicar();
	}

	document.querySelectorAll( 'form[data-filtrar-medicos]' ).forEach( filtrarMedicosPorEspecialidade );
} )();
