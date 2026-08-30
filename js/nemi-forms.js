document.addEventListener(
    'DOMContentLoaded',
    function () {


        /* =====================================================
           GENERIC RADIO CONDITIONAL
        ===================================================== */

        function radioValue(name) {

            const selected =
                document.querySelector(
                    'input[name="' +
                    name +
                    '"]:checked'
                );

            return selected
                ? selected.value
                : null;
        }


        function watchRadioConditional(
            radioName,
            targetId,
            showValue,
            activeClass
        ) {

            const target =
                document.getElementById(
                    targetId
                );

            if (!target) {
                return;
            }


            const radios =
                document.querySelectorAll(
                    'input[name="' +
                    radioName +
                    '"]'
                );


            function update() {

                target.classList.toggle(
                    activeClass,
                    radioValue(radioName)
                    === showValue
                );
            }


            radios.forEach(
                function (radio) {

                    radio.addEventListener(
                        'change',
                        update
                    );
                }
            );


            update();
        }



        /* =====================================================
           PROTECTION PERIOD
        ===================================================== */

        watchRadioConditional(
            'protection_period_choice',
            'protection-custom',
            'custom',
            'is-active'
        );



        /* =====================================================
           SHOWING INSTRUCTIONS
        ===================================================== */

        watchRadioConditional(
            'showing_instruction_choice',
            'showing-custom',
            'custom',
            'is-active'
        );

    }
);