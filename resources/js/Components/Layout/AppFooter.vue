<script setup>
const anio = new Date().getFullYear();
</script>

<template>
    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">{{ anio }} © SOGLOTEC.</div>
                <div class="col-sm-6">
                    <div class="text-sm-end d-none d-sm-block">
                        Diseñado y desarrollado por
                        <a href="tel:59169984601" target="_blank" rel="noopener" class="text-decoration-underline">SOGLOTEC</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</template>
