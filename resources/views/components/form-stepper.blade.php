@props(['steps' => [], 'currentStep' => 0])

<div class="sticky top-16 z-40 bg-white border-b border-gray-200 shadow-sm" x-data="formStepperData(@js($steps))">
    <div class="max-w-4xl mx-auto px-4 py-4">
        <nav aria-label="Progress">
            <ol role="list" class="flex items-center justify-between">
                <template x-for="(step, index) in steps" :key="step.id">
                    <li class="relative flex items-center" :class="index < steps.length - 1 ? 'flex-1' : ''">
                        {{-- Progress line --}}
                        <template x-if="index < steps.length - 1">
                            <div class="absolute top-4 left-8 right-0 h-0.5 transition-colors duration-300"
                                 :class="index < currentStep ? 'bg-primary-600' : 'bg-gray-200'" 
                                 aria-hidden="true"></div>
                        </template>

                        {{-- Step indicator --}}
                        <a :href="'#' + step.id"
                           @click.prevent="scrollToStep(step.id)"
                           class="relative flex items-center group focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 rounded-lg px-2 py-1"
                           :aria-current="index === currentStep ? 'step' : null">

                            {{-- Circle --}}
                            <div class="flex items-center justify-center w-8 h-8 rounded-full transition-colors duration-200"
                                 :class="{
                                     'bg-primary-600': index < currentStep,
                                     'bg-white border-2 border-primary-600': index === currentStep,
                                     'bg-white border-2 border-gray-300 group-hover:border-gray-400': index > currentStep
                                 }">
                                {{-- Checkmark for completed --}}
                                <template x-if="index < currentStep">
                                    <svg class="w-5 h-5 text-white" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </template>

                                {{-- Step number --}}
                                <template x-if="index >= currentStep">
                                    <span class="text-sm font-semibold"
                                          :class="{
                                              'text-primary-600': index === currentStep,
                                              'text-gray-500': index > currentStep
                                          }"
                                          x-text="index + 1"></span>
                                </template>
                            </div>

                            {{-- Label --}}
                            <span class="ml-3 text-sm font-medium hidden sm:inline transition-colors duration-200"
                                  :class="{
                                      'text-primary-600': index === currentStep,
                                      'text-gray-900': index < currentStep,
                                      'text-gray-500': index > currentStep
                                  }"
                                  x-text="step.label"></span>
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
