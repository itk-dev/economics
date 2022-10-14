/**
 * Toggle new customer and account form element based on checkbox state
 */
let checkboxCustomer = document.getElementById('create_project_form_new_customer');
let checkboxAccount = document.getElementById('create_project_form_new_account');

let accountElements = document.getElementsByClassName('form-group-account');
let customerElements = document.getElementsByClassName('form-group-customer');

// Listen to account checkbox.
checkboxAccount.addEventListener('change', function() {
	for (let element of accountElements) {
		element.classList.toggle('hidden');
	}

	// Also toggle the customer checkbox.
	checkboxCustomer.parentElement.classList.toggle('hidden');
});

// Listen to customer checkbox.
checkboxCustomer.addEventListener('change', function() {
	for (let element of customerElements) {
		element.classList.toggle('hidden');
	}
});
