// Al escribir, el campo deja de marcarse en rojo; el servidor vuelve a marcarlo si sigue mal en el próximo envío.
document.addEventListener('input', (event) => {
    event.target.closest?.('form[data-login-form] .fi-input-wrp.fi-invalid')?.classList.remove('fi-invalid');
});
