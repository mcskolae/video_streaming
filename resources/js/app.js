// Alpine.js est fourni par Livewire (@livewireScripts, présent dans tous les layouts) :
// le démarrer ici aussi le chargerait deux fois.

// Actions sensibles : un formulaire porteur de data-confirm demande confirmation avant d'être envoyé.
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
