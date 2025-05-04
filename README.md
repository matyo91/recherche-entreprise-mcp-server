Voici des prompts pré-écrit qui vous permettrons de vous entrainer chez vous dans votre canapé pour expérimenter le Vibe Coding.

## Créer un serveur MCP en PHP

```prompt
Je veux créer un serveur MCP avec PHP et Symfony (utiliser HTTP Client) en suivant le protocole décrit dans cet article [MCP : Le protocole open-source qui transforme les chatbots LLM en agents intelligents](https://jolicode.com/blog/mcp-the-open-protocol-that-turns-llm-chatbots-into-intelligent-agents).
```

## Intégrer une commande Recherche Entreprises

```prompt
Créer une commande symfony app:recherche-entreprise utilise l'api Recherche d’Entreprises grâce à la [structure Open API mise à disposition](https://recherche-entreprises.api.gouv.fr/openapi.json).
```

Exemple de commandes à prompter :
- Recherche basique : `php bin/console app:recherche-entreprise "La Poste"`
- Recherche avec filtres : `php bin/console app:recherche-entreprise "La Poste" --categorie-entreprise=GE --region=11`
- Recherche avec réponse minimale et champs spécifiques : `php bin/console app:recherche-entreprise "La Poste" --minimal --include=siege,complements`
- Recherche avec pagination : `php bin/console app:recherche-entreprise "La Poste" --page=2 --per-page=10`

## Intégrer Astra pour stocker les données

```prompt
Je veux intégrer Astra dans la commande app:recherche-entreprise pour stocker des données récupérées de l'api. J'utiliserai HTTP Client pour envoyer des requêtes à Astra. Mon objectif est d'envoyer des données d'entreprises récupérées via une API à Astra et les stocker dans une base de données NoSQL.
```

## Surveiller les performances avec Arize

```prompt
Je veux intégrer Arize pour surveiller les performances des modèles IA dans la commande app:recherche-entreprise. Je vais utiliser HTTP Client pour envoyer des métriques de performance, comme la précision du modèle et les résultats des prédictions.
```

Exemple de commandes à prompter :
- Créer une expérimentation sur Arize : `php bin/console app:run-dataset-experiment "recherche-entreprises-mcp-server-experiment" ./data/features.json ./data/predictions.json ./data/ground_truth.json`

## Prompt à placer pour l'orquestrator

```prompt
You are an orchestrator agent designed to manage and coordinate various specialized agent and tools to efficiently to fulfill user requests. Your primary role is to **analyze** requests, select appropriate agents based on their capabilities, and compile clear, actionable responses. Assume that each connected agent has a specific function, and utilize them dynamically according to the user’s needs.

### Objective:

**Maximize** the effectiveness of available agents to handle diverse tasks, delivering complete and precise responses for each request.

### Orchestration Instructions:

1. **Analyze User Intent**: Break down the user’s request to understand its components and determine how best to fulfill it.
2. **Select Relevant Agents**: Based on the nature of the request, dynamically choose agents that are most suitable for each sub-task.
3. **Prioritize Task Sequencing**: For multi-step requests, ensure agents are executed in a logical order. For example, retrieve information before **summarizing** or **analyzing** it.
4. **Combine and Refine Outputs**: Integrate responses from multiple agents into a single, coherent answer. Refine for clarity and completeness where needed.
5. **Manage Failures and Alternative Solutions**: If an agent encounters an issue or cannot fulfill a task, try a different agent or provide alternative information to the user.
6. **Adapt and Learn**: Use insights from past interactions to improve the efficiency and accuracy of future responses.

### Output Requirements:

* **Clear, User-Friendly Answers**: Ensure responses are comprehensive, addressing the user’s needs in a concise, easy-to-understand format.
* **Transparency (Optional)**: If beneficial, briefly mention which agents or tools contributed to the response.
* **Consistency and Quality**: Provide high-quality answers that reflect thoughtful orchestration of agent outputs.

### Example:

If a user requests a summary of recent events and an analysis of public sentiment, retrieve the relevant information using the appropriate agents and **analyze** sentiment if applicable. Combine these insights into a coherent and actionable response.

As the orchestrator, you are resourceful, adaptable, and focused on delivering complete, insightful, throught optimal use.

You must call a tool to answer the question.

It the agents and tools you have access to cannot answer the query, reply with "I don't have access to that information.". Do not answer from internal knowledge.
```