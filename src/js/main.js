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

/**
 * Envia um voto para um post através da API REST
 * @param {string} postId - ID do post a ser votado
 * @param {HTMLElement} object - Elemento HTML do botão de voto
 */
async function upVote(postId, object) {
  const url = `${orbitaApi.restURL}orbitaApi/v1/likes`;
  const formData = new URLSearchParams();
  formData.append('post_id', postId);

  const headers = {
    'Content-Type': 'application/x-www-form-urlencoded',
    'X-WP-Nonce': orbitaApi.restNonce,
  };

  try {
  const response = await fetch(url, {
    method: 'POST',
    headers,
    body: formData.toString(),
  });

  if (!response.ok) {
    throw new Error(`HTTP error! status: ${response.status}`);
  }

  const jsonData = await response.json();

  if (jsonData.success) {
    const textToUpdate = document.querySelector(
      `[data-votes-post-id='${postId}']`,
    );
    textToUpdate.innerHTML = jsonData.count;

    object.classList.add('orbita-vote-already-voted');
    object.classList.remove('orbita-vote-can-vote');
  }

  } catch (error) {
    throw new Error('Erro ao enviar voto:', error);
  }
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
  if (postAttachInput.value == '' && postUrlInput.value == '') {
    postUrlDiv.style.display = 'block';
    postAttachDiv.style.display = 'block';
  }
}

  // Some com o campo de link ou de imagem, depdende de qual for preenchido
  let postUrlInput = document.getElementById('orbita_post_url');
  let postAttachInput = document.getElementById('orbita_post_attach');
  const postUrlDiv = document.getElementById('orbita-form-post_url');
  const postAttachDiv = document.getElementById('orbita-form-post_attach');

  if (postUrlInput && postAttachInput) {
    toggleAttachUrl(postUrlInput);
    toggleAttachUrl(postAttachInput);

    postUrlInput.addEventListener('input', toggleAttachUrl);
    postAttachInput.addEventListener('input', toggleAttachUrl);
  }
