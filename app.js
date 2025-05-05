// Aplicação Vue
const app = Vue.createApp({
    data() {
        return {
            // Form data
            formData: {
                fullName: '',
                birthDate: ''
            },
            // Contact data for PDF
            contactData: {
                email: '',
                phone: ''
            },
            // App states
            isLoading: false,
            loadingMessage: '',
            showResults: false,
            showContactForm: false,
            // Results data
            results: {
                lifePathNumber: '',
                lifePathMeaning: '',
                lifePathTalents: '',
                destinyNumber: '',
                destinyMeaning: '',
                personalYearNumber: '',
                personalYearMeaning: '',
                currentChallenges: '',
                currentOpportunities: '',
                dailyRitual: '',
                transactionId: '' // Para referência do pagamento
            },
            // Mercado Pago SDK and responses
            mp: null,
            mpResponse: null,
            // Configuration (loaded from backend)
            apiEndpoint: '',    // will be fetched via getConfig
            mpPublicKey: '',     // Mercado Pago public key
            mpBaseUrl: '',       // base URL for redirect callbacks
            csrfToken: '',       // CSRF token
            paymentAmount: 0.15  // Valor da consulta
        };
    },
    mounted() {
        // Load config (CSRF token, Mercado Pago public key, API endpoint, base URL)
        fetch('api/api.php?action=getConfig')
          .then(res => res.json())
          .then(json => {
            if (json.success && json.config) {
              this.apiEndpoint  = json.config.apiEndpoint;
              this.csrfToken    = json.config.csrfToken;
              this.mpPublicKey  = json.config.mpPublicKey;
              this.mpBaseUrl    = json.config.mpBaseUrl;
              // Initialize Mercado Pago SDK
              this.mp = new MercadoPago(this.mpPublicKey, { locale: 'pt-BR' });
            } else {
              console.warn('Failed to load config:', json);
            }
          })
          .catch(err => console.warn('Error loading config:', err))
          .finally(() => {
            // Background stars and URL params only after config
            this.createStars();
            this.checkUrlParams();
          });
    },
    methods: {
        // Verificar parâmetros de URL
        checkUrlParams() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const paymentId = urlParams.get('payment_id');
            const externalReference = urlParams.get('external_reference');

            if (status === 'pending' && paymentId) {
                this.startPaymentPolling(paymentId);
            }

            if (status === 'approved' && paymentId) {
                // Pagamento aprovado, buscar resultados
                this.isLoading = true;
                this.loadingMessage = 'Carregando sua análise numerológica...';

                // Recuperar dados da sessão
                const savedFormData = sessionStorage.getItem('formData');
                if (savedFormData) {
                    this.formData = JSON.parse(savedFormData);

                    // Buscar os resultados
                    this.fetchNumerologyResults(paymentId, externalReference);
                } else {
                    // Se não tiver dados no sessionStorage, redirecionar para a página inicial
                    alert('Sessão expirada. Por favor, inicie uma nova consulta.');
                    this.isLoading = false;
                }
            } else if (status === 'rejected') {
                alert('Seu pagamento foi rejeitado. Por favor, tente novamente.');
            }
        },

        // Processar o formulário
        async processForm() {
            if (!this.formData.fullName || !this.formData.birthDate) {
                alert('Por favor, preencha todos os campos!');
                return;
            }

            // Salvar dados do formulário no sessionStorage
            sessionStorage.setItem('formData', JSON.stringify(this.formData));

            // Iniciar o processo de pagamento
            this.isLoading = true;
            this.loadingMessage = 'Preparando sua consulta...';

            try {
                // Chamar o backend para obter os resultados
                const response = await fetch(this.apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.csrfToken
                    },
                    body: JSON.stringify({
                        action: 'getTestResults',
                        formData: this.formData
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Erro ao buscar resultados');
                }

                // Atualizar resultados e sanitize HTML
                this.results = { ...this.results, ...data.results };
                // Sanitize any HTML before v-html
                Object.keys(this.results).forEach(key => {
                    if (typeof this.results[key] === 'string') {
                        this.results[key] = DOMPurify.sanitize(this.results[key]);
                    }
                });

                // Mostrar resultados
                this.isLoading = false;
                this.showResults = true;

                // Limpar parâmetros da URL para evitar recarregamentos acidentais
                window.history.replaceState({}, document.title, window.location.pathname);

            } catch (error) {
                console.error('Erro:', error);
                alert('Ocorreu um erro ao carregar sua análise. Por favor, tente novamente.');
                this.isLoading = false;
            }

            /*
            try {
                // Chamar o backend para criar a preferência de pagamento
                const response = await fetch(this.apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'createPayment',
                        amount: this.paymentAmount,
                        description: `Consulta Numerológica para ${this.formData.fullName}`,
                        formData: this.formData
                    })
                });
                // Verificar se a resposta foi bem-sucedida
                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status} ${response.statusText}`);
                }

                // Tentar analisar o JSON com tratamento de erro
                let data;
                try {
                    const text = await response.text();
                    console.log("Resposta do servidor:", text); // Debug
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error("Erro ao analisar JSON:", parseError);
                    throw new Error("Resposta inválida do servidor");
                }

                if (!data.success) {
                    throw new Error(data.message || 'Erro ao processar pagamento');
                }

                // Armazenar resposta do Mercado Pago
                this.mpResponse = data;

                // Iniciar o checkout do Mercado Pago
                this.startMercadoPagoCheckout();

            } catch (error) {
                console.error('Erro:', error);
                alert('Ocorreu um erro ao processar sua consulta. Por favor, tente novamente.');
                this.isLoading = false;
            }
            */
        },

        // Iniciar o checkout do Mercado Pago
        startMercadoPagoCheckout() {
            this.loadingMessage = 'Redirecionando para o pagamento...';

            // Redirecionar para a página de checkout do Mercado Pago
            if (this.mpResponse && this.mpResponse.init_point) {
                window.location.href = this.mpResponse.init_point;
            } else {
                alert('Ocorreu um erro ao iniciar o pagamento. Por favor, tente novamente.');
                this.isLoading = false;
            }
        },

        // Método para fazer polling do status do pagamento
        startPaymentPolling(paymentId) {
            this.isLoading = true;
            this.loadingMessage = 'Verificando status do pagamento...';
            
            // Recuperar dados do formulário da sessão
            const savedFormData = sessionStorage.getItem('formData');
            if (!savedFormData) {
                this.isLoading = false;
                return;
            }
            
            this.formData = JSON.parse(savedFormData);
            
            // Iniciar intervalo para verificar a cada 3 segundos
            const externalReference = new URLSearchParams(window.location.search).get('external_reference');
            let pollCount = 0;
            const maxPolls = 10; // Máximo de tentativas
            
            this.pollingInterval = setInterval(async () => {
                try {
                    pollCount++;
                    
                    const response = await fetch(this.apiEndpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'verifyPayment',
                            paymentId: paymentId,
                            externalReference: externalReference,
                            formData: this.formData
                        })
                    });
                    
                    if (!response.ok) {
                        throw new Error(`Erro HTTP: ${response.status}`);
                    }
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        if (data.paymentApproved) {
                            // Limpar o intervalo
                            clearInterval(this.pollingInterval);
                            
                            // Atualizar URL para refletir o status aprovado
                            const newUrl = window.location.pathname + 
                                '?status=approved&payment_id=' + paymentId + 
                                '&external_reference=' + (externalReference || '');
                            window.history.replaceState({}, document.title, newUrl);
                            
                            // Buscar resultados
                            this.fetchNumerologyResults(paymentId, externalReference);
                        } else if (pollCount >= maxPolls) {
                            // Parar após máximo de tentativas
                            clearInterval(this.pollingInterval);
                            this.loadingMessage = 'Pagamento pendente. Por favor, aguarde a confirmação do seu PIX.';
                            setTimeout(() => {
                                this.isLoading = false;
                            }, 3000);
                        } else {
                            this.loadingMessage = `Verificando pagamento... (${pollCount}/${maxPolls})`;
                        }
                    } else {
                        throw new Error(data.message || 'Erro ao verificar pagamento');
                    }
                } catch (error) {
                    console.error('Erro ao verificar pagamento:', error);
                    clearInterval(this.pollingInterval);
                    this.loadingMessage = 'Erro ao verificar pagamento. Atualizando em 5 segundos...';
                    
                    // Dar chance do usuário ver o erro antes de recarregar
                    setTimeout(() => {
                        window.location.reload();
                    }, 5000);
                }
            }, 3000); // Verificar a cada 3 segundos
        },

        // Buscar resultados da análise numerológica
        async fetchNumerologyResults(paymentId, externalReference) {
            try {
                // Chamar o backend para obter os resultados
                const response = await fetch(this.apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.csrfToken
                    },
                    body: JSON.stringify({
                        action: 'getNumerologyResults',
                        paymentId: paymentId,
                        externalReference: externalReference,
                        formData: this.formData
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Erro ao buscar resultados');
                }

                // Atualizar e sanitizar resultados
                this.results = { ...this.results, ...data.results, transactionId: paymentId };
                Object.keys(this.results).forEach(key => {
                    if (typeof this.results[key] === 'string') {
                        this.results[key] = DOMPurify.sanitize(this.results[key]);
                    }
                });

                // Mostrar resultados
                this.isLoading = false;
                this.showResults = true;

                // Limpar parâmetros da URL para evitar recarregamentos acidentais
                window.history.replaceState({}, document.title, window.location.pathname);

            } catch (error) {
                console.error('Erro:', error);
                alert('Ocorreu um erro ao carregar sua análise. Por favor, tente novamente.');
                this.isLoading = false;
            }
        },

        // Enviar informações de contato para receber PDF por email
        async sendContactInfo() {
            if (!this.contactData.email) {
                alert('Por favor, informe seu e-mail!');
                return;
            }

            this.isLoading = true;
            this.loadingMessage = 'Enviando análise por e-mail...';

            try {
                // Chamar o backend para enviar o PDF por email
                const response = await fetch(this.apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.csrfToken
                    },
                    body: JSON.stringify({
                        action: 'sendEmail',
                        formData: this.formData,
                        contactData: this.contactData,
                        results: this.results,
                        transactionId: this.results.transactionId
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Erro ao enviar email');
                }

                // Mostrar mensagem de sucesso
                alert('Sua análise foi enviada com sucesso para o e-mail informado!');
                this.showContactForm = false;
                this.isLoading = false;

            } catch (error) {
                console.error('Erro:', error);
                alert('Ocorreu um erro ao enviar o e-mail. Por favor, tente novamente.');
                this.isLoading = false;
            }
        },

        // Download do PDF
        async downloadPDF() {
            this.isLoading = true;
            this.loadingMessage = 'Gerando PDF para download...';

            try {
                // Chamar o backend para gerar o PDF
                const response = await fetch(this.apiEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': this.csrfToken
                    },
                    body: JSON.stringify({
                        action: 'generatePDF',
                        formData: this.formData,
                        results: this.results,
                        transactionId: this.results.transactionId
                    })
                });

                // Verificar se a resposta foi bem-sucedida
                if (!response.ok) {
                    throw new Error('Erro ao gerar PDF');
                }

                // Obter o blob do PDF
                const blob = await response.blob();

                // Criar URL para o blob
                const url = window.URL.createObjectURL(blob);

                // Criar link de download
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = `Analise_Numerologica_${this.formData.fullName.replace(/\s+/g, '_')}.pdf`;

                // Adicionar à página e clicar
                document.body.appendChild(a);
                a.click();

                // Limpar
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);

                this.isLoading = false;

            } catch (error) {
                console.error('Erro:', error);
                alert('Ocorreu um erro ao gerar o PDF. Por favor, tente novamente.');
                this.isLoading = false;
            }
        },

        // Resetar formulário para nova consulta
        resetForm() {
            this.formData = {
                fullName: '',
                birthDate: ''
            };
            this.contactData = {
                email: '',
                phone: ''
            };
            this.results = {
                lifePathNumber: '',
                lifePathMeaning: '',
                lifePathTalents: '',
                destinyNumber: '',
                destinyMeaning: '',
                personalYearNumber: '',
                personalYearMeaning: '',
                currentChallenges: '',
                currentOpportunities: '',
                dailyRitual: '',
                transactionId: ''
            };
            this.showResults = false;
            this.showContactForm = false;

            // Limpar dados da sessão
            sessionStorage.removeItem('formData');
        },

        // Formatação de data
        formatDate(dateString) {
            if (!dateString) return '';

            // Assume o formato 'YYYY-MM-DD' e evita conversão de timezone
            const [year, month, day] = dateString.split('-');
            return `${day}/${month}/${year}`;
        },

        // Criar efeito de estrelas no background
        createStars() {
            const container = this.$refs.starsContainer;
            const starCount = 100;

            for (let i = 0; i < starCount; i++) {
                const star = document.createElement('div');
                star.className = 'star';

                // Tamanho aleatório
                const size = Math.random() * 3 + 1;
                star.style.width = `${size}px`;
                star.style.height = `${size}px`;

                // Posição aleatória
                star.style.left = `${Math.random() * 100}%`;
                star.style.top = `${Math.random() * 100}%`;

                // Atraso na animação
                star.style.animationDelay = `${Math.random() * 2}s`;

                container.appendChild(star);
            }
        }
    },
    
    beforeDestroy() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
    },
});

// Montar a aplicação Vue
app.mount('#app');
