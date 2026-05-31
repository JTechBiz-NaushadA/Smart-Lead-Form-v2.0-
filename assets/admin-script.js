document.addEventListener("DOMContentLoaded", function () {

    // ===========================
    // Test Email Button
    // ===========================

    const testBtn = document.getElementById("tx-send-test");

    const currentFormField =
        document.getElementById("tx-current-form");

    const currentForm =
        currentFormField
            ? currentFormField.value
            : 'default';

    if (testBtn) {

        testBtn.addEventListener("click", function (e) {

            e.preventDefault();

            const emailField =
                document.getElementById("tx-test-email");

            const email = emailField.value.trim();

            const msgBox =
                document.getElementById("tx-test-msg");

            msgBox.innerText = "";
            msgBox.style.color = "";

            const emailRegex =
                /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!email) {

                msgBox.innerText =
                    "Please enter email";

                msgBox.style.color = "red";

                return;
            }

            if (!emailRegex.test(email)) {

                msgBox.innerText =
                    "Please enter a valid email address";

                msgBox.style.color = "red";

                return;
            }

            testBtn.disabled = true;
            testBtn.innerText = "Sending...";

            const formData = new URLSearchParams();

            formData.append("action", "tx_send_test");
            formData.append("email", email);
            formData.append("nonce", txAdmin.nonce);
            formData.append("form_key", currentForm);

            fetch(txAdmin.ajaxurl, {
                method: "POST",
                headers: {
                    "Content-Type":
                        "application/x-www-form-urlencoded"
                },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(res => {

                msgBox.innerText = res.data;

                msgBox.style.color =
                    res.success ? "green" : "red";
            })
            .catch(error => {

                console.log(error);

                msgBox.innerText =
                    "Something went wrong";

                msgBox.style.color = "red";
            })
            .finally(() => {

                testBtn.disabled = false;
                testBtn.innerText = "Send Test";
            });

        });

    }

    // ===========================
    // Delete Lead Buttons
    // ===========================
    const deleteBtns = document.querySelectorAll(".tx-delete-lead");
	deleteBtns.forEach(btn => {
		btn.addEventListener("click", function () {
			if (!confirm("Are you sure you want to delete this lead?")) return;

			const leadId = this.dataset.id;
			const row = this.closest("tr");
			const nonce = txAdmin.nonce;

			btn.disabled = true;
			const originalText = btn.innerText;
			btn.innerText = "Deleting...";

			fetch(txAdmin.ajaxurl, {
				method: "POST",
				headers: { "Content-Type": "application/x-www-form-urlencoded" },
				body: new URLSearchParams({
					action: "tx_delete_lead",
					id: leadId,
					nonce: nonce
				})
			})
			.then(res => res.json())
			.then(res => {
				if (res.success) {
					row.remove();
					alert(res.data);
				} else {
					alert(res.data);
				}
			})
			.catch(() => alert("Something went wrong"))
			.finally(() => {
				btn.disabled = false;
				btn.innerText = originalText;
			});
		});
	});
	
	// ===========================
	// Interest Chips Handler
	// ===========================
	document.querySelectorAll('.chip').forEach(chip => {
		chip.addEventListener('click', function () {

			this.classList.toggle('active');

			let selected = [];

			document.querySelectorAll('.chip.active').forEach(c => {
				selected.push(c.dataset.value);
			});

			document.getElementById('interests').value = JSON.stringify(selected);
		});
	});

});