require([
    'jquery'
], function ($) {
    'use strict';

    // Función que actualiza el título basado en el país
    function updateTitle(countryValue) {
        var newTitle = '';
        var titleField = $('#payment_other_openpay_stores_title'); // <-- USA EL ID QUE ENCONTRASTE

        // Mapeo de países a títulos
        var titlesByCountry = {
            'MX': 'Pago seguro con Efectivo',
            'PE': 'Pago en Agencias',
            'CO': 'Pago seguro con Efectivo'
        };

        // Si el país tiene un título definido, úsalo. Si no, deja el campo vacío o con un valor por defecto.
        newTitle = titlesByCountry[countryValue] || 'Pago en efectivo';
        
        // Asignamos el nuevo valor al campo de texto
        titleField.val(newTitle);
    }

    // Cuando el documento esté listo...
    $(document).ready(function () {
        // Obtenemos el selector del campo de país
        var countrySelector = $('#payment_other_openpay_stores_country'); // <-- USA EL ID QUE ENCONTRASTE

        // Ejecutamos la función una vez al cargar la página para establecer el estado inicial
        updateTitle(countrySelector.val());

        // Creamos un "listener" que se ejecuta cada vez que el valor del select cambia
        countrySelector.on('change', function () {
            updateTitle($(this).val());
        });
    });
});