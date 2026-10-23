<script setup>
const anio = new Date().getFullYear();
</script>

<template>
    <div class="auth-page-wrapper pt-5">
        <div id="auth-particles" class="auth-one-bg-position auth-one-bg">
            <div class="bg-overlay" />
            <div class="shape">
                <svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 1440 120" aria-hidden="true">
                    <path d="M 0,36 C 144,53.6 432,123.2 720,124 C 1008,124.8 1296,56.8 1440,40L1440 140L0 140z" />
                </svg>
            </div>
        </div>

        <div class="auth-page-content">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-center mt-sm-5 mb-4 text-white-50">
                            <img src="/felcc/img/felcc.png" alt="FELCC" height="72" class="d-inline-block">
                            <p class="mt-3 fs-15 fw-medium">Sistema de Gestión de Mandamientos de Aprehensión</p>
                        </div>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 col-xl-5">
                        <slot />
                    </div>
                </div>
            </div>
        </div>

        <footer class="footer">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-center">
                            <p class="mb-0 text-muted">&copy; {{ anio }} FELCC. Sistema de Gestión de Mandamientos</p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>
