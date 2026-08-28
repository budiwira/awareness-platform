<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({ exercise: Object, users: Array, phases: Object });

const errors = computed(() => usePage().props.errors ?? {});

const phaseKeys = ['planning', 'preparation', 'execution', 'evaluation', 'completed'];
const currentIndex = phaseKeys.indexOf(props.exercise.phase);

// --- Injects ---
const showInjectForm = ref(false);
const injectForm = ref({ title: '', description: '' });

const submitInject = () => {
    router.post(route('tenant.ttx.exercises.injects.store', props.exercise.id), injectForm.value, {
        onSuccess: () => {
            injectForm.value = { title: '', description: '' };
            showInjectForm.value = false;
        },
    });
};

// --- Teams ---
const showTeamForm = ref(false);
const teamForm = ref({ name: '', description: '' });

const submitTeam = () => {
    router.post(route('tenant.ttx.teams.store', props.exercise.id), teamForm.value, {
        onSuccess: () => {
            teamForm.value = { name: '', description: '' };
            showTeamForm.value = false;
        },
    });
};

// --- Members ---
const memberForms = ref({});

const memberForm = (teamId) => {
    if (!memberForms.value[teamId]) {
        memberForms.value[teamId] = { user_id: '', role_in_team: 'member' };
    }
    return memberForms.value[teamId];
};

const submitMember = (teamId) => {
    router.post(route('tenant.ttx.teams.members.store', teamId), memberForm(teamId), {
        onSuccess: () => (memberForm(teamId).user_id = ''),
    });
};
</script>