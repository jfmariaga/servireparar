import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

/**
 * Convención de notificaciones del sistema (SweetAlert2, sin CDN — instalado vía npm).
 * Úsala en cualquier módulo nuevo en lugar de alert()/confirm() nativos:
 *
 *   Notify.toast('success', 'Cliente creado correctamente.')
 *   Notify.confirmDanger({ title: '¿Inactivar cliente?', text: '...' }).then((ok) => ok && $wire.alternarEstado(id))
 */
const baseToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

const Notify = {
    /**
     * Notificación breve tras crear, editar, activar o inactivar un registro.
     * @param {'success'|'error'|'warning'|'info'} type
     */
    toast(type, message) {
        return baseToast.fire({ icon: type, title: message });
    },

    /**
     * Confirmación para pasos críticos (inactivar, eliminar, cancelar un proceso en curso, etc.).
     * Devuelve una promesa que resuelve en true si el usuario confirmó.
     */
    confirmDanger({ title, text = '', confirmButtonText = 'Sí, continuar' }) {
        return Swal.fire({
            title,
            text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#e0332c',
            cancelButtonColor: '#94a3b8',
            reverseButtons: true,
        }).then((result) => result.isConfirmed);
    },
};

window.Notify = Notify;

export default Notify;
