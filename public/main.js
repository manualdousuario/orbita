document.addEventListener(
  "DOMContentLoaded",
  function () {
    const titleTextArea = document.querySelector(".orbita-post-title-textarea");

    if (titleTextArea) {
      titleTextArea.addEventListener("keyup", (event) => {
        const charactersPerLine = event.target.offsetWidth / 10;
        event.target.rows = Math.round(event.target.value.length / charactersPerLine) + 1;
      });
    }

    const voteButtons = document.querySelectorAll(".orbita-vote-can-vote");

    voteButtons.forEach(function (button) {
      button.addEventListener("click", function (event) {
        upVote(button.dataset.postId, button);
        event.preventDefault();
        return false;
      });
    });
  },
  false
);

function upVote(postId, object) {
  // Exemplo de requisição POST
  const ajax = new XMLHttpRequest();

  // Seta tipo de requisição: Post e a URL da API
  ajax.open("POST", orbitaApi.restURL + 'orbitaApi/v1/likes', true);
  ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  ajax.setRequestHeader("X-WP-Nonce", orbitaApi.restNonce);

  // Seta paramêtros da requisição e envia a requisição
  ajax.send("post_id=" + postId);

  // Cria um evento para receber o retorno.
  ajax.onreadystatechange = function () {
    // Caso o state seja 4 e o http.status for 200, é porque a requisiçõe deu certo.
    if (ajax.readyState === 4 && ajax.status === 200) {
      const data = ajax.responseText;

      const jsonData = JSON.parse(data);
      if (jsonData.success) {
        const textToUpdate = document.querySelector(
          "[data-votes-post-id='" + postId + "']"
        );
        textToUpdate.innerHTML = jsonData.count;

        object.classList.add("orbita-vote-already-voted");
        object.classList.remove("orbita-vote-can-vote");
      }
    }
  };
}

// Verifica o tamanho da imagem anexada ao post antes de enviar
function verifyPostAttachFilesize() {
  const input = document.getElementById('orbita_post_attach');

  if (input.files.length > 0) {
    const file = input.files[0];
    const fileBytes = file.size;
    const fileMb = fileBytes / (1024 * 1024);

    if (fileMb > 10) {
      input.value = '';
      alert('O arquivo é maior que 10 MB');
    }
  }
}

// Some com o campo de link ou de imagem, depdende de qual for preenchido
let postUrlInput = document.getElementById('orbita_post_url');
let postAttachInput = document.getElementById('orbita_post_attach');
const postUrlDiv = document.getElementById('orbita-form-post_url');
const postAttachDiv = document.getElementById('orbita-form-post_attach');

postUrlInput.addEventListener('input', toggleAttachUrl);
postAttachInput.addEventListener('input', toggleAttachUrl);

function toggleAttachUrl() {
  verifyPostAttachFilesize();

  if (postUrlInput.value !== '') {
    postAttachDiv.style.display = 'none';
    postUrlDiv.style.display = 'block';
  }
  if (postAttachInput.value !== '') {
    postUrlDiv.style.display = 'none';
    postAttachDiv.style.display = 'block';
  }
  if(postAttachInput.value == '' && postUrlInput.value == ''){
    postUrlDiv.style.display = 'block';
    postAttachDiv.style.display = 'block';
  }
}
