<x-error-page
    code="403"
    accent="danger"
    title="Acesso negado"
    :message="$exception?->getMessage() ?: 'Você não tem permissão para acessar esta página.'" />
