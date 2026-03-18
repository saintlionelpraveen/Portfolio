# Kubernetes CI/CD Implementation Guide
### PHP App → Docker → Kubernetes with GitHub Actions + Jenkins

> **⚠️ Important:** This guide adds a **new, parallel CI/CD pipeline** for Kubernetes deployments.
> Your existing CI/CD workflows are **not modified in any way**.
> All new files use separate workflow names, separate Jenkins jobs, and separate triggers.
> Nothing in this guide touches your current pipeline configuration.

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Phase 1 — Containerize the PHP Application](#2-phase-1--containerize-the-php-application)
3. [Phase 2 — Kubernetes Cluster Setup](#3-phase-2--kubernetes-cluster-setup)
4. [Phase 3 — GitHub Actions CI (New Workflow Only)](#4-phase-3--github-actions-ci-new-workflow-only)
5. [Phase 4 — Approval Gate](#5-phase-4--approval-gate)
6. [Phase 5 — Jenkins CD Pipeline (New Job Only)](#6-phase-5--jenkins-cd-pipeline-new-job-only)
7. [Phase 6 — Triggering Jenkins from GitHub Actions](#7-phase-6--triggering-jenkins-from-github-actions)
8. [Namespace Targeting — Selective Pod Deployment](#8-namespace-targeting--selective-pod-deployment)
9. [Folder Structure Reference](#9-folder-structure-reference)
10. [Checklist](#10-checklist)

---

## 1. Prerequisites

Make sure the following are available before starting:

| Tool | Purpose |
|------|---------|
| Docker | Build and push images |
| kubectl | Interact with Kubernetes cluster |
| A Kubernetes cluster | k3s / minikube / cloud provider |
| GitHub repository | Source code + Actions |
| Jenkins server | CD pipeline (separate from existing) |
| Container registry | Docker Hub or GHCR |

---

## 2. Phase 1 — Containerize the PHP Application

### 2.1 Create the Dockerfile

In the root of your PHP project, create a file named `Dockerfile`:

```dockerfile
FROM php:8.2-apache

# Copy application code
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Enable Apache mod_rewrite (optional, if your app needs it)
RUN a2enmod rewrite

EXPOSE 80
```

### 2.2 Build and Test Locally

```bash
# Build the image
docker build -t php-app:local .

# Test it runs correctly
docker run -p 8080:80 php-app:local

# Open http://localhost:8080 and verify your app loads
```

### 2.3 Push to Container Registry

```bash
# Tag with registry path
docker tag php-app:local your-dockerhub-username/php-app:latest

# Login and push
docker login
docker push your-dockerhub-username/php-app:latest
```

> **Tip:** In the CI pipeline, images will be tagged with the Git commit SHA instead of `latest`
> so every build is fully traceable. Example: `your-registry/php-app:abc1234`

---

## 3. Phase 2 — Kubernetes Cluster Setup

### 3.1 Create Namespaces

Each application team or tenant gets its own namespace. Only that namespace is affected during deployment.

```bash
# Create namespace for team A
kubectl create namespace app-team-a

# Create namespace for team B
kubectl create namespace app-team-b

# Verify
kubectl get namespaces
```

### 3.2 Create the Deployment Manifest

Create `k8s/deployment.yaml` in your repository:

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: php-app
  # namespace is NOT hardcoded here — Jenkins will pass it via -n flag
spec:
  replicas: 2
  selector:
    matchLabels:
      app: php-app
  template:
    metadata:
      labels:
        app: php-app
    spec:
      containers:
        - name: php-app
          image: your-dockerhub-username/php-app:IMAGE_TAG
          ports:
            - containerPort: 80
          resources:
            requests:
              memory: "64Mi"
              cpu: "100m"
            limits:
              memory: "128Mi"
              cpu: "250m"
```

### 3.3 Create the Service Manifest

Create `k8s/service.yaml`:

```yaml
apiVersion: v1
kind: Service
metadata:
  name: php-app-service
spec:
  selector:
    app: php-app
  ports:
    - protocol: TCP
      port: 80
      targetPort: 80
  type: ClusterIP
```

### 3.4 Apply Initial Resources to Each Namespace

```bash
# Apply deployment to team A namespace
kubectl apply -f k8s/deployment.yaml -n app-team-a
kubectl apply -f k8s/service.yaml -n app-team-a

# Apply deployment to team B namespace
kubectl apply -f k8s/deployment.yaml -n app-team-b
kubectl apply -f k8s/service.yaml -n app-team-b

# Verify pods are running
kubectl get pods -n app-team-a
kubectl get pods -n app-team-b
```

---

## 4. Phase 3 — GitHub Actions CI (New Workflow Only)

> **⚠️ Do NOT edit your existing workflow files.**
> Create a brand new file with a different name. Your existing CI will continue to run independently.

### 4.1 Add GitHub Secrets

Go to your GitHub repository → **Settings → Secrets and variables → Actions** and add:

| Secret Name | Value |
|------------|-------|
| `REGISTRY_USERNAME` | Your Docker Hub username |
| `REGISTRY_PASSWORD` | Your Docker Hub password or token |
| `JENKINS_URL` | Your Jenkins server URL |
| `JENKINS_USER` | Jenkins username |
| `JENKINS_TOKEN` | Jenkins API token |

### 4.2 Create the New CI Workflow File

Create `.github/workflows/k8s-ci.yml` — this is a completely new file, separate from your existing workflow:

```yaml
# -------------------------------------------------------
# NEW WORKFLOW — Kubernetes CI Pipeline
# This file is separate from your existing CI/CD.
# It only triggers on merged PRs that have the label
# "deploy-to-k8s" applied — so your normal PRs are
# completely unaffected.
# -------------------------------------------------------

name: K8s CI — Build and Push Image

on:
  pull_request:
    types: [closed]

# Only run this workflow if:
# 1. The PR was actually merged (not just closed)
# 2. The PR has the label "deploy-to-k8s"
jobs:
  build-and-push:
    if: |
      github.event.pull_request.merged == true &&
      contains(github.event.pull_request.labels.*.name, 'deploy-to-k8s')

    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Set image tag from commit SHA
        run: echo "IMAGE_TAG=${{ github.sha }}" >> $GITHUB_ENV

      - name: Log in to Docker Hub
        uses: docker/login-action@v3
        with:
          username: ${{ secrets.REGISTRY_USERNAME }}
          password: ${{ secrets.REGISTRY_PASSWORD }}

      - name: Build Docker image
        run: |
          docker build -t ${{ secrets.REGISTRY_USERNAME }}/php-app:${{ env.IMAGE_TAG }} .

      - name: Push Docker image
        run: |
          docker push ${{ secrets.REGISTRY_USERNAME }}/php-app:${{ env.IMAGE_TAG }}

      - name: Output image tag for next stage
        run: echo "Built and pushed image with tag ${{ env.IMAGE_TAG }}"
```

> **How isolation works:** The `contains(...labels..., 'deploy-to-k8s')` condition means this workflow
> only fires when a PR is explicitly labelled for Kubernetes deployment. All your regular PRs without
> that label will continue to use your existing CI pipeline, completely untouched.

---

## 5. Phase 4 — Approval Gate

The approval gate pauses the pipeline after CI and waits for a human to approve before CD begins.

### 5.1 Create a GitHub Environment with Protection Rules

1. Go to **Repository → Settings → Environments**
2. Click **New environment** → name it `k8s-production`
3. Enable **Required reviewers** and add the people who should approve deployments
4. Save

### 5.2 Add the Approval Stage to the CI Workflow

Append this second job to your `k8s-ci.yml` file (after the `build-and-push` job):

```yaml
  # This job waits for a human to approve in the GitHub UI
  # before triggering Jenkins
  await-approval:
    needs: build-and-push
    runs-on: ubuntu-latest
    environment: k8s-production   # <-- this triggers the approval gate

    steps:
      - name: Approval granted — proceeding to CD trigger
        run: echo "Deployment approved. Triggering Jenkins..."
```

Once a reviewer approves in the GitHub Actions UI, the next job (Jenkins trigger) will automatically start.

---

## 6. Phase 5 — Jenkins CD Pipeline (New Job Only)

> **⚠️ Create a brand new Jenkins job.** Do not modify your existing Jenkins jobs.
> Name it something clearly distinct, for example `php-k8s-deploy`.

### 6.1 Install Required Jenkins Plugins

In Jenkins → **Manage Jenkins → Plugins**, install:

- **Kubernetes CLI Plugin** — for `kubectl` commands
- **Pipeline** — for Jenkinsfile support

### 6.2 Configure kubectl Credentials in Jenkins

1. Jenkins → **Manage Jenkins → Credentials**
2. Add a **Secret file** credential
3. Upload your `~/.kube/config` file
4. Set the ID to `kubeconfig-credential`

### 6.3 Create the Jenkinsfile

Create `Jenkinsfile.k8s` in your repository root (separate from any existing `Jenkinsfile`):

```groovy
// -------------------------------------------------------
// NEW JENKINS PIPELINE — Kubernetes CD
// This is a separate pipeline from your existing CD.
// It is only triggered by the k8s GitHub Actions workflow.
// -------------------------------------------------------

pipeline {
    agent any

    parameters {
        choice(
            name: 'TARGET_NAMESPACE',
            choices: ['app-team-a', 'app-team-b'],
            description: 'Which namespace should receive this deployment?'
        )
        string(
            name: 'IMAGE_TAG',
            defaultValue: '',
            description: 'Docker image tag (Git commit SHA from CI)'
        )
        string(
            name: 'REGISTRY_USERNAME',
            defaultValue: 'your-dockerhub-username',
            description: 'Container registry username'
        )
    }

    stages {

        stage('Validate inputs') {
            steps {
                script {
                    if (!params.IMAGE_TAG) {
                        error("IMAGE_TAG parameter is required")
                    }
                    if (!params.TARGET_NAMESPACE) {
                        error("TARGET_NAMESPACE parameter is required")
                    }
                }
                echo "Deploying image tag: ${params.IMAGE_TAG}"
                echo "Target namespace:    ${params.TARGET_NAMESPACE}"
            }
        }

        stage('Deploy to namespace') {
            steps {
                withKubeConfig([credentialsId: 'kubeconfig-credential']) {
                    sh """
                        # Update only the pods inside the target namespace
                        # All other namespaces are completely untouched
                        kubectl set image deployment/php-app \
                            php-app=${params.REGISTRY_USERNAME}/php-app:${params.IMAGE_TAG} \
                            -n ${params.TARGET_NAMESPACE}
                    """
                }
            }
        }

        stage('Verify rollout') {
            steps {
                withKubeConfig([credentialsId: 'kubeconfig-credential']) {
                    sh """
                        # Wait until the new pods are healthy
                        kubectl rollout status deployment/php-app \
                            -n ${params.TARGET_NAMESPACE} \
                            --timeout=120s
                    """
                }
            }
        }

        stage('Confirm running pods') {
            steps {
                withKubeConfig([credentialsId: 'kubeconfig-credential']) {
                    sh "kubectl get pods -n ${params.TARGET_NAMESPACE}"
                }
            }
        }
    }

    post {
        success {
            echo "Deployment to ${params.TARGET_NAMESPACE} succeeded."
        }
        failure {
            echo "Deployment to ${params.TARGET_NAMESPACE} FAILED. Rolling back..."
            withKubeConfig([credentialsId: 'kubeconfig-credential']) {
                sh "kubectl rollout undo deployment/php-app -n ${params.TARGET_NAMESPACE}"
            }
        }
    }
}
```

### 6.4 Create the Jenkins Job

1. Jenkins → **New Item** → name it `php-k8s-deploy` → choose **Pipeline**
2. Under **Pipeline definition**, select **Pipeline script from SCM**
3. Point it to your repository
4. Set **Script Path** to `Jenkinsfile.k8s`
5. Under **This project is parameterized**, add the three parameters matching the Jenkinsfile
6. Enable **Trigger builds remotely** and note the authentication token

---

## 7. Phase 6 — Triggering Jenkins from GitHub Actions

Add a final job to `k8s-ci.yml` that fires after approval:

```yaml
  trigger-jenkins-cd:
    needs: await-approval
    runs-on: ubuntu-latest

    steps:
      - name: Determine target namespace from PR label
        id: get-namespace
        run: |
          # Reads the PR label to decide which namespace to deploy to.
          # Label format expected: "ns:app-team-a" or "ns:app-team-b"
          LABEL="${{ github.event.pull_request.labels[*].name }}"
          NAMESPACE=$(echo "$LABEL" | grep -oP 'ns:\K[^ ]+' || echo "app-team-a")
          echo "NAMESPACE=$NAMESPACE" >> $GITHUB_ENV

      - name: Trigger Jenkins CD pipeline
        run: |
          curl -X POST \
            "${{ secrets.JENKINS_URL }}/job/php-k8s-deploy/buildWithParameters" \
            --user "${{ secrets.JENKINS_USER }}:${{ secrets.JENKINS_TOKEN }}" \
            --data "TARGET_NAMESPACE=${{ env.NAMESPACE }}" \
            --data "IMAGE_TAG=${{ github.sha }}" \
            --data "REGISTRY_USERNAME=${{ secrets.REGISTRY_USERNAME }}"
```

### How to Label a PR for Namespace Targeting

When raising a PR, add a label in this format:

| Label | Deploys to |
|-------|-----------|
| `deploy-to-k8s` | Required to even trigger this workflow |
| `ns:app-team-a` | Deploys only to `app-team-a` namespace |
| `ns:app-team-b` | Deploys only to `app-team-b` namespace |

If no `ns:` label is found, the pipeline defaults to `app-team-a` as a safe fallback.

---

## 8. Namespace Targeting — Selective Pod Deployment

This is the core isolation mechanism. `kubectl set image` only rolls out new pods inside the namespace you specify with `-n`. Every other namespace stays on its current version.

```
kubectl set image deployment/php-app php-app=your-registry/php-app:NEW_TAG -n app-team-a
                                                                              ^^^^^^^^^^^^^
                                                              Only these pods are updated.
                                                              app-team-b is completely untouched.
```

### Rollback a Specific Namespace

```bash
# Roll back only app-team-a — app-team-b stays on its current version
kubectl rollout undo deployment/php-app -n app-team-a
```

### Check Current Image Version Per Namespace

```bash
kubectl get deployment php-app -n app-team-a -o=jsonpath='{.spec.template.spec.containers[0].image}'
kubectl get deployment php-app -n app-team-b -o=jsonpath='{.spec.template.spec.containers[0].image}'
```

---

## 9. Folder Structure Reference

```
your-php-repo/
│
├── .github/
│   └── workflows/
│       ├── existing-ci.yml          ← YOUR EXISTING CI — DO NOT TOUCH
│       └── k8s-ci.yml               ← NEW: Kubernetes CI + Approval + Jenkins trigger
│
├── k8s/
│   ├── deployment.yaml              ← Kubernetes Deployment manifest
│   └── service.yaml                 ← Kubernetes Service manifest
│
├── Jenkinsfile                      ← YOUR EXISTING JENKINSFILE — DO NOT TOUCH
├── Jenkinsfile.k8s                  ← NEW: Kubernetes CD pipeline only
├── Dockerfile                       ← NEW: Containerize your PHP app
└── index.php                        ← Your existing PHP application
```

---

## 10. Checklist

Work through this in order. Check each item before moving to the next.

### Infrastructure
- [ ] Docker installed and working locally
- [ ] Kubernetes cluster running (`kubectl cluster-info`)
- [ ] Namespaces created (`app-team-a`, `app-team-b`)
- [ ] Initial deployment and service applied to each namespace
- [ ] Container registry account ready (Docker Hub / GHCR)

### Containerization
- [ ] `Dockerfile` created and tested locally
- [ ] Image builds successfully (`docker build`)
- [ ] App works inside container (`docker run`)
- [ ] Image pushed to registry manually once to verify

### GitHub Actions (New Workflow)
- [ ] All secrets added to repository (`REGISTRY_USERNAME`, `REGISTRY_PASSWORD`, `JENKINS_URL`, `JENKINS_USER`, `JENKINS_TOKEN`)
- [ ] `k8s-ci.yml` created (new file, NOT editing existing workflow)
- [ ] GitHub Environment `k8s-production` created with required reviewers
- [ ] `deploy-to-k8s` label created in the repository
- [ ] `ns:app-team-a` and `ns:app-team-b` labels created in the repository

### Jenkins (New Job)
- [ ] Kubernetes CLI plugin installed
- [ ] `kubeconfig` credential uploaded to Jenkins
- [ ] `Jenkinsfile.k8s` created (separate from existing Jenkinsfile)
- [ ] New Jenkins job `php-k8s-deploy` created pointing to `Jenkinsfile.k8s`
- [ ] Remote trigger token configured in Jenkins job
- [ ] Manual test: trigger Jenkins job manually with a namespace and image tag, verify only that namespace updates

### End-to-End Test
- [ ] Raise a test PR with labels `deploy-to-k8s` and `ns:app-team-a`
- [ ] Merge the PR
- [ ] Verify `k8s-ci.yml` workflow starts (existing CI should still run independently)
- [ ] Verify approval gate appears in GitHub Actions UI
- [ ] Approve the deployment
- [ ] Verify Jenkins `php-k8s-deploy` job triggers
- [ ] Verify only `app-team-a` pods updated (`kubectl get pods -n app-team-a`)
- [ ] Verify `app-team-b` pods are unchanged (`kubectl get pods -n app-team-b`)
- [ ] Verify existing CI/CD pipeline still works on a regular PR (without the k8s label)

---

> **Reminder on isolation:** The two things that keep this completely separate from your existing pipeline are:
> (1) the `contains(...labels..., 'deploy-to-k8s')` guard in the workflow trigger, and
> (2) using a brand new `Jenkinsfile.k8s` and a brand new Jenkins job `php-k8s-deploy`.
> As long as you do not modify your existing workflow files or Jenkinsfile, there is zero risk of interference.
