/**
 * Ecrã de carregamento (impresso inline no <head> por inc/loader.php).
 *
 * Tapa a página até ao evento load; se as imagens demorarem, sai no máximo
 * 2,5 s depois do DOMContentLoaded. window.clinicaPronto resolve quando o ecrã
 * sai, para que as animações de entrada comecem à vista e não por baixo dele.
 */
(function (documento, janela) {
	var raiz = documento.documentElement;
	var resolver;
	var saiu = false;

	janela.clinicaPronto = new Promise(function (r) {
		resolver = r;
	});
	raiz.classList.add('cv-carregando');

	function sair() {
		if (saiu) {
			return;
		}
		saiu = true;
		raiz.classList.remove('cv-carregando');
		resolver();
		setTimeout(function () {
			var ecra = documento.getElementById('cv-loader');
			if (ecra) {
				ecra.remove();
			}
		}, 450);
	}

	janela.addEventListener('load', sair);
	documento.addEventListener('DOMContentLoaded', function () {
		setTimeout(sair, 2500);
	});
	setTimeout(sair, 8000);
})(document, window);
