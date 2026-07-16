Technical Solution Document (TSD)

# 1. Feature: Real Connections Feed

## Objective

The goal of the Real Connections Feed is to provide users with a personalized social feed that prioritizes meaningful content instead of popularity. Unlike traditional social media platforms where posts are ranked based on likes, shares, or comments, this feed focuses on genuine relationships, authentic content, user interests, and recency.

The feed ranking is based on four main factors:

- Authenticity of the post

- Relationship depth between users

- Semantic similarity using AI embeddings

- Time decay

The application consists of a React Native mobile app, Laravel PHP backend, Python AI service, SQL database, and a Vector Database for semantic search.

![Architecture diagram](media/image1.png)

# 2. Database Schema Design

## Users

| Column        | Type      |
|---------------|-----------|
| id            | BIGINT    |
| name          | VARCHAR   |
| email         | VARCHAR   |
| profile_image | VARCHAR   |
| created_at    | TIMESTAMP |

Index

- Primary Key (id)

- Email (Unique)

## Posts

| Column             | Type      |
|--------------------|-----------|
| id                 | BIGINT    |
| user_id            | BIGINT    |
| caption            | TEXT      |
| image_url          | VARCHAR   |
| authenticity_score | FLOAT     |
| created_at         | TIMESTAMP |

Indexes

- user_id

- created_at

## User_Interactions

Stores meaningful interactions between users.

| Column           | Type      |
|------------------|-----------|
| id               | BIGINT    |
| user_id          | BIGINT    |
| target_user_id   | BIGINT    |
| interaction_type | VARCHAR   |
| created_at       | TIMESTAMP |

Examples:

- Direct Message

- Reply

- Mention

- Profile Visit

- Conversation

Indexes

- user_id

- target_user_id

## Relationships

| Column             | Type   |
|--------------------|--------|
| user_id            | BIGINT |
| connected_user_id  | BIGINT |
| relationship_score | FLOAT  |

The relationship score increases based on meaningful interactions instead of simply following another user.

## Post_Embeddings

| Column    | Type    |
|-----------|---------|
| post_id   | BIGINT  |
| vector_id | VARCHAR |

The actual embedding vector is stored in the vector database.

## Database Relationships

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<tbody>
<tr>
<td><p>Users</p>
<p>|</p>
<p>|--- 1 : N ---&gt; Posts</p>
<p>|</p>
<p>|--- 1 : N ---&gt; User_Interactions</p>
<p>|</p>
<p>+--- N : N ---&gt; Relationships</p>
<p>Posts</p>
<p>|</p>
<p>+--- 1 : 1 ---&gt; Post_Embeddings</p></td>
</tr>
</tbody>
</table>

# 3. Vector Embeddings

## Why Use Vector Embeddings?

Traditional SQL searches depend on keyword matching. This often fails when users use different words with the same meaning.

Example:

Search:

> *“Funny travel stories”*

A normal keyword search may not return a post saying:

> *“Our hilarious Goa vacation.”*

Even though both have the same meaning.

Embeddings convert text into numerical vectors that represent semantic meaning rather than exact words.

## Embedding Model

I would use OpenAI's text-embedding-3-small model.

### Reasons

- High semantic accuracy

- Fast response time

- Cost-effective

- Easy integration with Python

- Suitable for recommendation systems

## Vector Database

I would use Qdrant.

### Why Qdrant?

- Open source

- Excellent vector similarity search

- REST API support

- Easy integration with Laravel and Python

- Fast performance even with large datasets

- Simple deployment using Docker

## Embedding Workflow

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<tbody>
<tr>
<td><p>User creates a post</p>
<p>|</p>
<p>v</p>
<p>Laravel stores post</p>
<p>|</p>
<p>v</p>
<p>Python generates embedding</p>
<p>|</p>
<p>v</p>
<p>Embedding stored in Qdrant</p>
<p>|</p>
<p>v</p>
<p>Vector ID stored in SQL</p></td>
</tr>
</tbody>
</table>

# 4. API Design

## Authentication

JWT Token Authentication

Example Header

|                                     |
|-------------------------------------|
| Authorization: Bearer \<JWT_TOKEN\> |

## Get Personalized Feed

GET /api/feed

### Response

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<tbody>
<tr>
<td><p>{</p>
<p>"posts": [</p>
<p>{</p>
<p>"id": 101,</p>
<p>"author": "John",</p>
<p>"caption": "Weekend hiking adventure!",</p>
<p>"score": 0.92</p>
<p>}</p>
<p>]</p>
<p>}</p></td>
</tr>
</tbody>
</table>

## Create Post

POST /api/posts

### Request

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<tbody>
<tr>
<td><p>{</p>
<p>"caption": "Beautiful sunset today",</p>
<p>"image_url": "image.jpg"</p>
<p>}</p></td>
</tr>
</tbody>
</table>

## Natural Language Search

POST /api/feed/search

### Request

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<tbody>
<tr>
<td><p>{</p>
<p>"query": "funny travel stories from last week"</p>
<p>}</p></td>
</tr>
</tbody>
</table>

### Response

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<tbody>
<tr>
<td><p>{</p>
<p>"results": [</p>
<p>{</p>
<p>"id": 45,</p>
<p>"caption": "Our funniest Goa trip",</p>
<p>"similarity": 0.94</p>
<p>}</p>
<p>]</p>
<p>}</p></td>
</tr>
</tbody>
</table>

## Record User Interaction

POST /api/interactions

### Request

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<tbody>
<tr>
<td><p>{</p>
<p>"target_user_id": 20,</p>
<p>"interaction_type": "reply"</p>
<p>}</p></td>
</tr>
</tbody>
</table>

# 5. Feed Ranking Algorithm

## Ranking Logic (Plain English)

Each post is assigned a score using four ranking factors.

### 1. Authenticity Score

Posts that appear more genuine receive higher priority.

Examples include:

- Minimal image filters

- Natural-looking photos

- Personal stories

- Honest captions

Overly edited or promotional content receives a lower score.

### 2. Relationship Depth

The system measures how closely two users interact.

Factors include:

- Direct messages

- Replies

- Conversations

- Profile visits

- Frequent interactions

Simply following another user should not significantly increase ranking.

### 3. Semantic Similarity

Every user has an interest profile generated from:

- Previous searches

- Viewed posts

- Liked posts

- Saved content

Each post also has an embedding.

The closer the user's embedding is to the post embedding, the higher the relevance score.

### 4. Time Decay

Recent posts receive a small boost.

However, highly relevant posts should still appear above newer but less relevant posts.

## Final Ranking Formula

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<tbody>
<tr>
<td><p>Final Score =</p>
<p>40% Relationship Score</p>
<p>+ 30% Semantic Similarity</p>
<p>+ 20% Authenticity Score</p>
<p>+ 10% Time Decay</p></td>
</tr>
</tbody>
</table>

## Pseudocode

<table>
<colgroup>
<col style="width: 100%" />
</colgroup>
<tbody>
<tr>
<td><p>for each candidate post</p>
<p>relationshipScore = calculateRelationship(user, author)</p>
<p>semanticScore = cosineSimilarity(</p>
<p>userEmbedding,</p>
<p>postEmbedding</p>
<p>)</p>
<p>authenticityScore = calculateAuthenticity(post)</p>
<p>freshnessScore = calculateTimeDecay(post)</p>
<p>finalScore =</p>
<p>relationshipScore * 0.40</p>
<p>+ semanticScore * 0.30</p>
<p>+ authenticityScore * 0.20</p>
<p>+ freshnessScore * 0.10</p>
<p>sort all posts by finalScore</p>
<p>return ranked feed</p></td>
</tr>
</tbody>
</table>

# 6. AI Agentic Tools Used

To improve development speed while maintaining code quality, I would use the following AI-assisted tools:

### ChatGPT

- Brainstorming the overall architecture

- Designing the database schema

- Drafting API structures

- Refining the feed ranking logic

- Preparing technical documentation

### GitHub Copilot

- Generating boilerplate Laravel and React Native code

- Creating CRUD operations

- Assisting with repetitive coding tasks

- Writing unit test templates

### Cursor AI

- Understanding unfamiliar code

- Refactoring existing functions

- Suggesting optimizations

- Detecting potential bugs

### Why Use These Tools?

These AI tools help reduce development time by handling repetitive work and providing implementation suggestions. However, every generated solution would still be reviewed, tested, and validated before being merged into production to ensure correctness, security, and maintainability.

# Conclusion

The proposed solution builds a personalized feed that values meaningful relationships over popularity. By combining relationship depth, authenticity signals, semantic understanding through vector embeddings, and time-based relevance, the platform delivers a more genuine and engaging user experience. The architecture is modular, scalable, and designed to support future AI-driven enhancements while maintaining clean separation between the mobile application, backend services, AI processing, and data storage.
