@props(['steps' => [], 'currentStep' => 0])

<div 
    class="sticky top-16 z-40 bg-white/90 backdrop-blur-md border-b border-gray-200/80 shadow-[0_1px_2px_rgba(0,0,0,0.05)] transition-all duration-300" 
    x-data="formStepperData(@js($steps))"
>
    <div class="max-w-4xl mx-auto px-4 py-4 sm:px-6">
        <nav aria-label="Progress">
            <ol role="list" class="flex items-center">
                <template x-for="(step, index) in steps" :key="step.id">
                    <li class="relative flex items-center" :class="index < steps.length - 1 ? 'flex-1' : ''">
                        
                        {{-- Connector Line --}}
                        <template x-if="index < steps.length - 1">
                            <div class="absolute top-1/2 left-0 w-[calc(100%-2rem)] ml-8 -translate-y-1/2 h-[2px] bg-gray-100 rounded-full overflow-hidden" aria-hidden="true">
                                <div 
                                    class="h-full bg-primary-600 transition-all duration-500 ease-out origin-left"
                                    :class="index < currentStep ? 'w-full' : 'w-0'"
                                ></div>
                            </div>
                        </template>

                        {{-- Step Item --}}
                        <a 
                            :href="'#' + step.id"
                            @click.prevent="scrollToStep(step.id)"
                            class="group relative flex items-center focus:outline-none"
                            :aria-current="index === currentStep ? 'step' : null"
                        >
                            {{-- Circle Indicator --}}
                            <span 
                                class="relative flex items-center justify-center w-8 h-8 rounded-full border-2 text-sm transition-all duration-300 ease-in-out"
                                :class="{
                                    'bg-primary-600 border-primary-600 text-white shadow-md scale-110 ring-2 ring-primary-100 ring-offset-2': index === currentStep,
                                    'bg-primary-600 border-primary-600 text-white': index < currentStep,
                                    'bg-white border-gray-200 text-gray-400 group-hover:border-primary-300 group-hover:text-gray-500': index > currentStep
                                }"
                            >
                                {{-- Completed Checkmark --}}
                                <template x-if="index < currentStep">
                                    <svg class="w-4 h-4 transition-transform duration-300 ease-out scale-100" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </template>

                                {{-- Step Number --}}
                                <template x-if="index >= currentStep">
                                    <span class="font-bold font-sans" x-text="index + 1"></span>
                                </template>
                            </span>

                            {{-- Label --}}
                            <span 
                                class="ml-3 text-sm font-medium hidden sm:inline-block transition-colors duration-300"
                                :class="{
                                    'text-primary-700 font-semibold': index === currentStep,
                                    'text-gray-900': index < currentStep,
                                    'text-gray-500 group-hover:text-gray-700': index > currentStep
                                }"
                                x-text="step.label"
                            ></span>
                        </a>
                    </li>
                </template>
            </ol>
        </nav>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('formStepperData', (initialSteps) => ({
        currentStep: 0,
        steps: initialSteps || [],
        observer: null,

        init() {
            this.setupObserver();
            // Check initial scroll position
            this.checkVisibleSection();
        },

        setupObserver() {
            // Intersection Observer to track which section is currently visible
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const stepId = entry.target.id;
                        const stepIndex = this.steps.findIndex(s => s.id === stepId);
                        if (stepIndex !== -1) {
                            this.currentStep = stepIndex;
                        }
                    }
                });
            }, {
                threshold: 0.5,
                rootMargin: '-100px 0px -50% 0px'
            });

            // Observe all step sections
            this.steps.forEach(step => {
                const element = document.getElementById(step.id);
                if (element) {
                    this.observer.observe(element);
                }
            });
        },

        scrollToStep(stepId) {
            const element = document.getElementById(stepId);
            if (element) {
                const offset = 100; // Account for sticky header
                const elementPosition = element.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - offset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
        },

        checkVisibleSection() {
            // Find which section is currently in view
            this.steps.forEach((step, index) => {
                const element = document.getElementById(step.id);
                if (element) {
                    const rect = element.getBoundingClientRect();
                    const isVisible = rect.top < window.innerHeight / 2 && rect.bottom > window.innerHeight / 2;
                    if (isVisible) {
                        this.currentStep = index;
                    }
                }
            });
        }
    }));
});
</script>
