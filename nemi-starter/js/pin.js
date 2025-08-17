document.addEventListener("DOMContentLoaded", () => {
  const inputs = document.querySelectorAll(".pin-inputs input");
  inputs.forEach((input, idx) => {
    input.addEventListener("input", () => {
      if (input.value && idx < inputs.length - 1) {
        inputs[idx + 1].focus();
      }
    });
  });
});