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

        event.preventDefault;
        return false;
      });
    });

    const reportButtons = document.querySelectorAll(".orbita-report-link");

    reportButtons.forEach(function (button) {
      button.addEventListener("click", function (event) {
        report(
          button.dataset.commentId,
          button.dataset.postId,
          button
        );

        button.classList.add("orbita-report-link-already-reported");
        button.innerHTML = "Ok! Obrigado.";
        button.disabled = true;

        event.preventDefault;
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
    if (ajax.readyState == 4 && ajax.status == 200) {
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

function report(commentId, postId, object) {
  // Exemplo de requisição POST
  const ajax = new XMLHttpRequest();

  // Seta tipo de requisição: Post e a URL da API
  ajax.open("POST", orbitaApi.restURL + 'orbitaApi/v1/report', true);
  ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  ajax.setRequestHeader("X-WP-Nonce", orbitaApi.restNonce);

  // Seta paramêtros da requisição e envia a requisição
  ajax.send(
    "comment_id=" + commentId + "&post_id=" + postId
  );
}
