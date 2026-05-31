document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll(".tx-form-smart-lead-plugin").forEach(formEl => {

        let chips = formEl.querySelectorAll(".chips span");
        let hidden = formEl.querySelector('input[name="interests"]');
        let msgBox = formEl.querySelector(".tx-message");
        let btn = formEl.querySelector("button");

        /* Chips */
        chips.forEach(c => {

            c.onclick = () => {

                c.classList.toggle("active");
                update();
            };
        });

        function update() {

            let vals = [];

            formEl.querySelectorAll(".chips .active").forEach(el => {
                vals.push(el.dataset.value);
            });

            if (hidden) {
                hidden.value = JSON.stringify(vals);
            }
        }

        /* AJAX Submit */
        formEl.onsubmit = function (e) {

            e.preventDefault();

            let form = new FormData(formEl);

            form.append('action', 'tx_submit');
            form.append('nonce', tx_ajax.nonce);

            btn.disabled = true;
            btn.classList.add("loading");
            btn.innerHTML = "Processing...";

            fetch(tx_ajax.url, {
                method: 'POST',
                body: form
            })
            .then(res => res.json())
            .then(res => {

                showMessage(res.data, "success");

                formEl.reset();

                if (hidden) {
                    hidden.value = "";
                }

                formEl.querySelectorAll(".chips span").forEach(c => {
                    c.classList.remove("active");
                });

            })
            .catch(() => {

                showMessage("Something went wrong. Try again.", "error");

            })
            .finally(() => {

                btn.disabled = false;
                btn.classList.remove("loading");
                btn.innerHTML = "Download Now!";
            });
        };

        /* Message UI */
        function showMessage(text, type) {

            msgBox.innerHTML = text;
            msgBox.className = "tx-message " + type;
            msgBox.style.display = "block";

            setTimeout(() => {
                msgBox.style.opacity = "0";
            }, 10000);

            setTimeout(() => {
                msgBox.style.display = "none";
                msgBox.style.opacity = "1";
            }, 11000);
        }

    });

});